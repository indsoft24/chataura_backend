<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomMember extends Model
{
    use HasFactory, HasUuids;

    public const ROLE_HOST = 'host';
    public const ROLE_CO_HOST = 'co_host';
    public const ROLE_SPEAKER = 'speaker';
    public const ROLE_LISTENER = 'listener';

    protected $fillable = [
        'room_id',
        'user_id',
        'role',
        'seat_index',
        'joined_at',
        'left_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'seat_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the room.
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

