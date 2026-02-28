<?php

namespace App\Services;

use App\Events\RoomClosed;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Seat;
use Illuminate\Support\Facades\DB;

/**
 * End room: status=ended, mark all members left, free seats, broadcast room_closed.
 */
class DestroyRoomService
{
    public function endRoom(Room $room): void
    {
        DB::transaction(function () use ($room) {
            if ($room->status === Room::STATUS_ENDED) {
                return;
            }

            $room->status = Room::STATUS_ENDED;
            $room->ended_at = now();
            $room->is_live = false;
            $room->save();

            RoomMember::where('room_id', $room->id)
                ->where('is_active', true)
                ->update(['left_at' => now(), 'is_active' => false]);

            Seat::where('room_id', $room->id)
                ->update(['user_id' => null, 'is_muted' => false]);

            event(new RoomClosed($room));
        });
    }
}
