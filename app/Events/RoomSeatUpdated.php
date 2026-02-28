<?php

namespace App\Events;

use App\Models\Room;
use App\Models\Seat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomSeatUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public Seat $seat
    ) {}

    public function broadcastAs(): string
    {
        return 'seat_updated';
    }
}
