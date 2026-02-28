<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomMember;

/**
 * Room lifecycle: last activity, room-alive checks.
 */
class RoomService
{
    public function updateLastActivity(Room $room): void
    {
        $room->last_activity_at = now();
        $room->save();
    }

    /**
     * Room must end when: no active host AND no active seated members (speaker/co_host).
     * Listeners do NOT keep room alive.
     */
    public function shouldEndRoom(Room $room): bool
    {
        if ($room->status !== Room::STATUS_ACTIVE) {
            return false;
        }

        $hasActiveHost = RoomMember::where('room_id', $room->id)
            ->where('user_id', $room->host_id)
            ->where('is_active', true)
            ->where('role', RoomMember::ROLE_HOST)
            ->exists();

        if ($hasActiveHost) {
            return false;
        }

        $hasSeatedMember = RoomMember::where('room_id', $room->id)
            ->where('is_active', true)
            ->whereIn('role', [RoomMember::ROLE_CO_HOST, RoomMember::ROLE_SPEAKER])
            ->exists();

        return !$hasSeatedMember;
    }

    /**
     * Check if user is current host (host_id on room).
     */
    public function isHost(Room $room, int $userId): bool
    {
        return (int) $room->host_id === $userId;
    }
}
