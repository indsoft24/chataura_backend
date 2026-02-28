<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'display_id',
        'title',
        'owner_id',
        'host_id',
        'agora_channel_name',
        'agora_channel_uid',
        'max_seats',
        'is_live',
        'status',
        'cover_image_url',
        'theme_id',
        'description',
        'tags',
        'settings',
        'allowed_gender',
        'allowed_country',
        'min_age',
        'max_age',
        'ended_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'max_seats' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'agora_channel_uid' => 'integer',
            'tags' => 'array',
            'settings' => 'array',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the owner (creator) of the room.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the current host of the room (authority for permissions).
     */
    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * Get the room theme (dynamic theme for background/animation).
     */
    public function theme()
    {
        return $this->belongsTo(RoomTheme::class, 'theme_id');
    }

    /**
     * Get the members of the room.
     */
    public function members()
    {
        return $this->hasMany(RoomMember::class);
    }

    /**
     * Get active members (not left).
     */
    public function activeMembers()
    {
        return $this->hasMany(RoomMember::class)->where('is_active', true);
    }

    /**
     * Active seated members (host, co_host, speaker) — listeners do not keep room alive.
     */
    public function activeSeatedMembers()
    {
        return $this->hasMany(RoomMember::class)
            ->where('is_active', true)
            ->whereIn('role', [RoomMember::ROLE_HOST, RoomMember::ROLE_CO_HOST, RoomMember::ROLE_SPEAKER]);
    }

    /**
     * Get the seats in the room.
     */
    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    /**
     * Get transactions in this room.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the current host user (host_id, or owner if null).
     */
    public function getCurrentHostUser(): ?User
    {
        if ($this->host_id) {
            return $this->host;
        }
        return $this->owner;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->is_live && !$this->trashed();
    }

    /**
     * Find a room by UUID (id) or by short display_id.
     */
    public static function findByIdOrDisplayId(string $idOrDisplayId): ?self
    {
        return static::where('id', $idOrDisplayId)
            ->orWhere('display_id', $idOrDisplayId)
            ->first();
    }
}

