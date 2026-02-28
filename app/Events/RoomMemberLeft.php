<?php

namespace App\Events;

use App\Models\Room;
use App\Models\RoomMember;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMemberLeft
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Room $room,
        public RoomMember $member
    ) {}

    public function broadcastAs(): string
    {
        return 'member_left';
    }
}
