<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_EMOJI = 'emoji';
    public const TYPE_GIFT = 'gift';
    public const TYPE_SYSTEM = 'system';

    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message_type',
        'message_text',
        'message_media',
        'message',
        'image_url',
        'gift_id',
        'status',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
