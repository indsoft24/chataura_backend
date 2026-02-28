<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    protected $table = 'calls';

    public const TYPE_AUDIO = 'audio';
    public const TYPE_VIDEO = 'video';

    public const STATUS_RINGING = 'ringing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ENDED = 'ended';
    public const STATUS_MISSED = 'missed';

    /** Statuses that count as "active" (no duplicate call allowed). */
    public const ACTIVE_STATUSES = [self::STATUS_RINGING, self::STATUS_ACCEPTED];

    protected $fillable = [
        'caller_id',
        'receiver_id',
        'channel_name',
        'agora_token',
        'call_type',
        'status',
        'started_at',
        'ended_at',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
