<?php

namespace App\Services;

use App\Models\ConversationParticipant;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private ?string $serverKey;

    /**
     * Send high-priority FCM data push to a single token (Legacy HTTP API).
     * Use for incoming call and new message when app closed/background.
     * All data values are cast to string for FCM.
     */
    public static function sendPush(string $fcmToken, array $data): void
    {
        $serverKey = config('services.fcm.server_key');
        if (empty($serverKey) || empty($fcmToken)) {
            return;
        }
        $data = array_map(fn ($v) => (string) $v, $data);
        try {
            Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $fcmToken,
                'priority' => 'high',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FirebaseService::sendPush failed', ['message' => $e->getMessage()]);
        }
    }

    public function __construct()
    {
        $this->serverKey = config('services.fcm.server_key');
    }

    /** Whether legacy FCM (server key) is configured. Avoid calling send methods when false to prevent log warnings. */
    public function isConfigured(): bool
    {
        return !empty($this->serverKey);
    }

    /**
     * Send FCM notification to all devices of a user.
     *
     * @param int $userId Target user's database ID
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array<string, string> $data Optional key-value data payload
     * @return bool True if at least one send succeeded
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $tokens = UserDevice::where('user_id', $userId)->pluck('fcm_token')->filter()->unique()->values()->all();
        if (empty($tokens)) {
            Log::debug('FirebaseService: No FCM tokens for user', ['user_id' => $userId]);
            return false;
        }
        if (empty($this->serverKey)) {
            Log::debug('FirebaseService: FCM_SERVER_KEY not configured');
            return false;
        }
        $sent = false;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send to a single FCM token (Legacy HTTP API).
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if (empty($this->serverKey)) {
            return false;
        }
        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $data,
            'priority' => 'high',
        ];
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);
            if (!$response->successful()) {
                Log::warning('FirebaseService: FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }
            $json = $response->json();
            if (isset($json['failure']) && $json['failure'] > 0) {
                Log::warning('FirebaseService: FCM failure', ['response' => $json]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('FirebaseService: FCM exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send real-time message payload to all other participants in the conversation.
     * Called synchronously after message is saved. Do not wait for queue.
     *
     * @param int $conversationId
     * @param array<string, mixed> $messageObject Full message payload for client (id, sender_id, message_type, message_text, message_media, created_at, etc.)
     */
    public function sendRealtimeMessage(int $conversationId, array $messageObject): void
    {
        $recipientIds = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', '!=', $messageObject['sender_id'] ?? 0)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $title = $messageObject['sender_name'] ?? 'New message';
        $body = isset($messageObject['message_text']) && (string) $messageObject['message_text'] !== ''
            ? mb_substr((string) $messageObject['message_text'], 0, 100)
            : ($messageObject['message_type'] === 'image' ? '📷 Image' : 'New message');
        $data = [
            'type' => 'realtime_message',
            'conversation_id' => (string) $conversationId,
            'message_id' => (string) ($messageObject['id'] ?? ''),
            'sender_id' => (string) ($messageObject['sender_id'] ?? ''),
        ];
        foreach (['message_type', 'message_text', 'message_media', 'created_at'] as $key) {
            if (array_key_exists($key, $messageObject) && $messageObject[$key] !== null) {
                $data[$key] = is_string($messageObject[$key]) ? $messageObject[$key] : json_encode($messageObject[$key]);
            }
        }

        foreach ($recipientIds as $userId) {
            $this->sendToUser((int) $userId, $title, $body, $data);
        }
    }

    /**
     * Send message push notification to a user (e.g. new chat message).
     * Call when new message is sent.
     *
     * @param int $userId Target user ID
     * @param array<string, mixed> $message Message payload (id, sender_id, sender_name, message_type, message_text, conversation_id, created_at, etc.)
     */
    public function sendMessageNotification(int $userId, array $message): bool
    {
        $title = $message['sender_name'] ?? 'New message';
        $body = isset($message['message_text']) && (string) $message['message_text'] !== ''
            ? mb_substr((string) $message['message_text'], 0, 100)
            : 'New message';
        $data = [
            'type' => 'message',
            'conversation_id' => (string) ($message['conversation_id'] ?? ''),
            'message_id' => (string) ($message['id'] ?? ''),
            'sender_id' => (string) ($message['sender_id'] ?? ''),
        ];
        foreach (['message_type', 'message_text', 'created_at'] as $key) {
            if (array_key_exists($key, $message) && $message[$key] !== null) {
                $data[$key] = is_string($message[$key]) ? $message[$key] : json_encode($message[$key]);
            }
        }
        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send incoming call push notification to a user.
     * Call when call is initiated.
     *
     * @param int $userId Callee user ID
     * @param array<string, mixed> $callData Call payload (call_id, conversation_id, caller_id, caller_name, channel_name, call_type, token, uid, etc.)
     */
    public function sendCallNotification(int $userId, array $callData): bool
    {
        $title = $callData['caller_name'] ?? 'Incoming call';
        $body = ($callData['call_type'] ?? 'audio') === 'video' ? 'Incoming video call' : 'Incoming voice call';
        $data = [
            'type' => 'call',
            'call_id' => (string) ($callData['call_id'] ?? ''),
            'conversation_id' => (string) ($callData['conversation_id'] ?? ''),
            'caller_id' => (string) ($callData['caller_id'] ?? ''),
            'channel_name' => (string) ($callData['channel_name'] ?? ''),
            'call_type' => (string) ($callData['call_type'] ?? 'audio'),
            'token' => (string) ($callData['token'] ?? ''),
            'uid' => (string) ($callData['uid'] ?? ''),
        ];
        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send incoming call push notification with type "incoming_call" and high priority.
     * Data-only (no notification block) so Android always delivers to onMessageReceived and CallForegroundService can start.
     *
     * @param int $receiverId Callee user ID
     * @param array<string, mixed> $payload Must include: call_id, channel_name, caller_id, caller_name, call_type
     */
    public function sendIncomingCallNotification(int $receiverId, array $payload): bool
    {
        $data = [
            'type' => 'incoming_call',
            'call_id' => (string) ($payload['call_id'] ?? ''),
            'channel_name' => (string) ($payload['channel_name'] ?? ''),
            'caller_id' => (string) ($payload['caller_id'] ?? ''),
            'caller_name' => (string) ($payload['caller_name'] ?? ''),
            'call_type' => (string) ($payload['call_type'] ?? 'audio'),
        ];
        if (!empty($payload['conversation_id'])) {
            $data['conversation_id'] = (string) $payload['conversation_id'];
        }
        if (!empty($payload['token'])) {
            $data['token'] = (string) $payload['token'];
        }
        if (isset($payload['uid'])) {
            $data['uid'] = (string) $payload['uid'];
        }
        $tokens = UserDevice::where('user_id', $receiverId)->pluck('fcm_token')->filter()->unique()->values()->all();
        $userToken = \App\Models\User::find($receiverId)?->fcm_token;
        if (!empty($userToken) && !in_array($userToken, $tokens, true)) {
            $tokens[] = $userToken;
        }
        if (empty($tokens) || empty($this->serverKey)) {
            return false;
        }
        $sent = false;
        foreach ($tokens as $token) {
            if ($this->sendToTokenDataOnly($token, $data, 'high')) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send data-only FCM (no notification block). Use for incoming call so onMessageReceived is always called.
     */
    public function sendToTokenDataOnly(string $token, array $data, string $priority = 'high'): bool
    {
        if (empty($this->serverKey)) {
            return false;
        }
        $payload = [
            'to' => $token,
            'data' => $data,
            'priority' => $priority,
        ];
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);
            if (!$response->successful()) {
                Log::warning('FirebaseService: FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }
            $json = $response->json();
            if (isset($json['failure']) && $json['failure'] > 0) {
                Log::warning('FirebaseService: FCM failure', ['response' => $json]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('FirebaseService: FCM exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send to a single FCM token with explicit priority (high for instant delivery).
     */
    public function sendToTokenWithPriority(string $token, string $title, string $body, array $data, string $priority = 'high'): bool
    {
        if (empty($this->serverKey)) {
            return false;
        }
        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $data,
            'priority' => $priority,
        ];
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);
            if (!$response->successful()) {
                Log::warning('FirebaseService: FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }
            $json = $response->json();
            if (isset($json['failure']) && $json['failure'] > 0) {
                Log::warning('FirebaseService: FCM failure', ['response' => $json]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('FirebaseService: FCM exception', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
