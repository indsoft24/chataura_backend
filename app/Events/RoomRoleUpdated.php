<?php

namespace App\Events;

use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomRoleUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public RoomMember $member
    ) {}

    /**
     * Event name for broadcasting so frontend can update UI privileges (e.g. co-host can manage seats, themes, music).
     */
    public function broadcastAs(): string
    {
        return 'room_role_updated';
    }
}
