<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'room_id',
        'gift_type_id',
        'quantity',
        'coin_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'coin_amount' => 'integer',
        ];
    }

    /**
     * Get the sender.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver.
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Get the room.
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the gift type.
     */
    public function giftType()
    {
        return $this->belongsTo(GiftType::class);
    }
}

