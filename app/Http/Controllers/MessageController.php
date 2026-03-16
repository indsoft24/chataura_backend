<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\BunnyStorageService;
use App\Services\FirebaseCallService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function __construct(
        private FirebaseService $firebase,
        private FirebaseCallService $firebaseCall,
        private BunnyStorageService $bunny
    ) {}

    /**
     * GET /messages/{conversation_id} - List messages, newest first. Paginated.
     * Query: page (default 1), limit (default 20, max 100).
     * Includes sender_id, sender_name, sender_avatar, message_type, message_text, message_media, status.
     */
    public function index(Request $request, int $conversation_id)
    {
        $user = $request->user();
        $participant = ConversationParticipant::where('conversation_id', $conversation_id)->where('user_id', $user->id)->first();
        if (!$participant) {
            return ApiResponse::forbidden('Not a participant of this conversation');
        }
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        $query = Message::where('conversation_id', $conversation_id)->with('sender');
        $total = $query->count();
        $messages = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->sortBy('created_at')
            ->values();

        $list = $messages->map(function (Message $m) {
            $sender = $m->sender;
            $row = [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'sender_id' => $m->sender_id,
                'sender_name' => $sender ? ($sender->display_name ?? $sender->name) : null,
                'sender_avatar' => $sender ? $sender->avatar_url : null,
                'message_type' => $m->message_type ?? 'text',
                'message_text' => $m->message_text ?? $m->message,
                'gift_id' => $m->gift_id,
                'message_media' => $m->message_media ?? $m->image_url,
                'status' => $m->status ?? 'sent',
                'created_at' => $m->created_at->toIso8601String(),
            ];
            $row['message'] = $row['message_text'];
            $row['image_url'] = $row['message_media'];
            return $row;
        });

        return ApiResponse::success($list->values()->all(), ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * POST /messages/upload-image - Multipart: image (file). Returns URL for use in send.
     * Stored under chat-attachments (legacy) or use chat_media via send with message_media file.
     */
    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|max:10240', // 10MB
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $file = $request->file('image');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = 'chat_media/' . (string) Str::ulid() . '.' . $ext;
        try {
            $url = $this->bunny->uploadImage($file, $path);
        } catch (\Throwable $e) {
            return ApiResponse::error('UPLOAD_FAILED', 'Failed to store image on CDN', 500);
        }
        return ApiResponse::success(['url' => $url]);
    }

    /**
     * POST /messages/send - Send message (text, emoji, gift only). Saves to DB, triggers Firebase push instantly, returns message.
     * Body: conversation_id (required), message_type (text|emoji|gift), message_text, gift_id (optional for gift).
     * Only conversation participants can send. Backward compat: "message" accepted as message_text.
     */
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'conversation_id' => 'required|integer|exists:conversations,id',
                'message_type' => 'nullable|string|in:text,emoji,gift|max:20',
                'message_text' => 'nullable|string|max:65535',
                'gift_id' => 'nullable|integer',
                'message' => 'nullable|string|max:65535',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $participant = ConversationParticipant::where('conversation_id', $validated['conversation_id'])->where('user_id', $user->id)->first();
        if (!$participant) {
            return ApiResponse::forbidden('Not a participant of this conversation');
        }

        $messageType = $validated['message_type'] ?? 'text';
        $messageText = $validated['message_text'] ?? $validated['message'] ?? '';
        $giftId = $validated['gift_id'] ?? null;

        if ($messageType === 'gift' && $giftId === null && $messageText === '') {
            return ApiResponse::error('VALIDATION_FAILED', 'gift_id or message_text required for gift', 400);
        }
        if (in_array($messageType, ['text', 'emoji'], true) && $messageText === '') {
            return ApiResponse::error('VALIDATION_FAILED', 'message_text is required', 400);
        }

        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'sender_id' => $user->id,
            'message_type' => $messageType,
            'message_text' => $messageText ?: null,
            'gift_id' => $giftId,
            'message' => $messageText,
            'status' => Message::STATUS_SENT,
        ]);
        $message->load('sender');

        $messagePayload = [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->display_name ?? $message->sender->name ?? 'User',
            'message_type' => $message->message_type,
            'message_text' => $message->message_text,
            'gift_id' => $message->gift_id,
            'created_at' => $message->created_at->toIso8601String(),
        ];
        $recipientIds = ConversationParticipant::where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id')
            ->all();
        foreach ($recipientIds as $recipientId) {
            $recipientId = (int) $recipientId;
            if ($this->firebaseCall->isConfigured()) {
                $this->firebaseCall->sendNewMessageNotification($recipientId, array_merge($messagePayload, [
                    'message' => $message->message_text ?? '',
                ]));
            } elseif ($this->firebase->isConfigured()) {
                $this->firebase->sendMessageNotification($recipientId, $messagePayload);
            }
        }

        return ApiResponse::success([
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'message_type' => $message->message_type,
            'message_text' => $message->message_text,
            'gift_id' => $message->gift_id,
            'status' => $message->status,
            'created_at' => $message->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /messages/status - Update message status (delivered, read).
     */
    public function status(Request $request)
    {
        try {
            $validated = $request->validate([
                'message_id' => 'required|integer|exists:messages,id',
                'status' => 'required|string|in:delivered,read',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $message = Message::findOrFail($validated['message_id']);
        $user = $request->user();
        $participant = ConversationParticipant::where('conversation_id', $message->conversation_id)->where('user_id', $user->id)->first();
        if (!$participant) {
            return ApiResponse::forbidden('Not a participant of this conversation');
        }
        $message->status = $validated['status'];
        $message->save();
        return ApiResponse::success([
            'id' => $message->id,
            'status' => $message->status,
        ]);
    }
}
