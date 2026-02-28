<?php

namespace App\Jobs;

use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $messageId,
        public int $conversationId,
        public int $senderId,
        public string $senderName,
        public string $messagePreview,
        public ?string $imageUrl = null
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        $recipientIds = ConversationParticipant::where('conversation_id', $this->conversationId)
            ->where('user_id', '!=', $this->senderId)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $title = $this->senderName;
        $body = $this->messagePreview;
        if ($this->imageUrl) {
            $body = '📷 ' . ($body ?: 'Sent an image');
        }
        $data = [
            'type' => 'message',
            'conversation_id' => (string) $this->conversationId,
            'message_id' => (string) $this->messageId,
            'sender_id' => (string) $this->senderId,
        ];

        foreach ($recipientIds as $userId) {
            $firebase->sendToUser($userId, $title, $body, $data);
        }
    }
}
