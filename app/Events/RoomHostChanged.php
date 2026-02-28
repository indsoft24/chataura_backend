<?php

namespace App\Events;

use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomHostChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public ?User $newHost
    ) {}

    /**
     * Event name for broadcasting: host_changed.
     */
    public function broadcastAs(): string
    {
        return 'host_changed';
    }
}
