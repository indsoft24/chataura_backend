<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'room_id',
        'seat_index',
        'user_id',
        'is_muted',
        'is_locked',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_index' => 'integer',
            'is_muted' => 'boolean',
            'is_locked' => 'boolean',
            'last_heartbeat_at' => 'datetime',
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
     * Get the user occupying this seat.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

