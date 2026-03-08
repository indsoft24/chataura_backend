<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * FCM via kreait/laravel-firebase (correct OAuth2 scope, no invalid_scope).
 * Incoming call = DATA-ONLY so Android always delivers to onMessageReceived.
 */
class FirebaseCallService
{
    /**
     * Collect all FCM tokens for a user: UserDevice rows + users.fcm_token.
     *
     * @return array<int, string>
     */
    private function getTokensForUser(int $userId): array
    {
        $tokens = UserDevice::where('user_id', $userId)->pluck('fcm_token')->filter()->unique()->values()->all();
        $userToken = User::find($userId)?->fcm_token;
        if (!empty($userToken) && !in_array($userToken, $tokens, true)) {
            $tokens[] = $userToken;
        }
        return $tokens;
    }

    /**
     * Send incoming call notification as DATA-ONLY FCM (no notification block).
     * Data-only messages always call onMessageReceived on Android, even when app is in background.
     * Keeps existing API: (userId, callData) for CallController.
     */
    public function sendIncomingCallNotification(int $userId, array $callData): bool
    {
        $tokens = $this->getTokensForUser($userId);
        if (empty($tokens)) {
            return false;
        }

        $data = [
            'type' => 'incoming_call',
            'call_id' => (string) ($callData['call_id'] ?? ''),
            'channel_name' => (string) ($callData['channel_name'] ?? ''),
            'call_type' => (string) ($callData['call_type'] ?? 'audio'),
            'caller_name' => (string) ($callData['caller_name'] ?? ''),
            'caller_id' => (string) ($callData['caller_id'] ?? ''),
            'conversation_id' => (string) ($callData['conversation_id'] ?? ''),
            'token' => (string) ($callData['token'] ?? ''),
            'uid' => (string) ($callData['uid'] ?? ''),
        ];

        $sent = false;
        foreach ($tokens as $deviceToken) {
            if ($this->sendIncomingCallToToken($deviceToken, $data)) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send data-only incoming call to one device token.
     */
    private function sendIncomingCallToToken(string $deviceToken, array $data): bool
    {
        if (empty($deviceToken)) {
            return false;
        }
        try {
            $messaging = app('firebase.messaging');
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withData($data)
                ->withAndroidConfig(['priority' => 'high']);
            $messaging->send($message);
            return true;
        } catch (MessagingException $e) {
            Log::error('FirebaseCallService: MessagingException: ' . $e->getMessage());
            return false;
        } catch (FirebaseException $e) {
            Log::error('FirebaseCallService: FirebaseException: ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::error('FirebaseCallService: Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send call ended/cancelled signal to one device token (data-only).
     */
    public function sendCallEnded(string $deviceToken, int $callId, string $reason = 'call_ended'): bool
    {
        if (empty($deviceToken)) {
            return false;
        }
        try {
            $messaging = app('firebase.messaging');
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withData([
                    'type' => $reason,
                    'call_id' => (string) $callId,
                ])
                ->withAndroidConfig(['priority' => 'high']);
            $messaging->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('FirebaseCallService: sendCallEnded failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send call_ended FCM to all devices of a user (e.g. other participant when call ends or is rejected).
     * Data-only so Android always delivers to onMessageReceived.
     */
    public function sendCallEndedToUser(int $userId, int $callId, string $reason = 'call_ended'): bool
    {
        $tokens = $this->getTokensForUser($userId);
        if (empty($tokens)) {
            return false;
        }
        $sent = false;
        foreach ($tokens as $deviceToken) {
            if ($this->sendCallEnded($deviceToken, $callId, $reason)) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send new message notification (with notification block — shows in tray when app closed).
     * Keeps existing API: (userId, messageData) for MessageController.
     */
    public function sendNewMessageNotification(int $userId, array $messageData): bool
    {
        $tokens = $this->getTokensForUser($userId);
        if (empty($tokens)) {
            return false;
        }

        $data = [
            'type' => 'new_message',
            'conversation_id' => (string) ($messageData['conversation_id'] ?? ''),
            'sender_id' => (string) ($messageData['sender_id'] ?? ''),
            'sender_name' => (string) ($messageData['sender_name'] ?? ''),
            'message' => (string) ($messageData['message'] ?? $messageData['message_text'] ?? ''),
        ];
        if (!empty($messageData['id'])) {
            $data['message_id'] = (string) $messageData['id'];
        }
        // FCM reserves "message_type" — use msg_type in data payload
        if (isset($messageData['message_type'])) {
            $data['msg_type'] = (string) $messageData['message_type'];
        }

        $title = $messageData['sender_name'] ?? 'New message';
        $body = isset($messageData['message_text']) && (string) $messageData['message_text'] !== ''
            ? mb_substr((string) $messageData['message_text'], 0, 100)
            : 'New message';

        $sent = false;
        foreach ($tokens as $deviceToken) {
            if ($this->sendMessageNotificationToToken($deviceToken, $title, $body, $data)) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send one new-message FCM (notification + data) to a device token.
     */
    private function sendMessageNotificationToToken(string $deviceToken, string $title, string $body, array $data): bool
    {
        if (empty($deviceToken)) {
            return false;
        }
        try {
            $messaging = app('firebase.messaging');
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($data)
                ->withAndroidConfig(['priority' => 'high']);
            $messaging->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('FirebaseCallService: sendMessageNotification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether Firebase (kreait) is configured and usable.
     */
    public function isConfigured(): bool
    {
        try {
            $project = config('firebase.default', 'app');
            $cred = config("firebase.projects.{$project}.credentials");
            if (empty($cred) || !is_string($cred)) {
                return false;
            }
            $path = str_starts_with($cred, '/') ? $cred : base_path($cred);
            return is_file($path);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
