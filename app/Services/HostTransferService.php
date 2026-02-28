<?php

namespace App\Services;

use App\Events\RoomHostChanged;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Host authority: transfer on leave, manual transfer. All in transaction.
 */
class HostTransferService
{

    /**
     * When host leaves: transfer to earliest co_host, else earliest speaker. If none, return false (caller should end room).
     */
    public function transferOnHostLeave(Room $room): bool
    {
        return DB::transaction(function () use ($room) {
            $newHostMember = RoomMember::where('room_id', $room->id)
                ->where('is_active', true)
                ->where('user_id', '!=', $room->host_id)
                ->whereIn('role', [RoomMember::ROLE_CO_HOST, RoomMember::ROLE_SPEAKER])
                ->orderBy('joined_at')
                ->first();

            if (!$newHostMember) {
                return false;
            }

            $this->assignHost($room, $newHostMember->user_id);
            event(new RoomHostChanged($room, User::find($newHostMember->user_id)));
            return true;
        });
    }

    /**
     * Manual transfer: current user must be host. New host gets host role; old host downgraded to speaker.
     */
    public function manualTransfer(Room $room, int $currentUserId, int $newHostUserId): void
    {
        DB::transaction(function () use ($room, $currentUserId, $newHostUserId) {
            if ((int) $room->host_id !== $currentUserId) {
                throw new \RuntimeException('Only current host can transfer host');
            }

            $newHostMember = RoomMember::where('room_id', $room->id)
                ->where('user_id', $newHostUserId)
                ->where('is_active', true)
                ->first();

            if (!$newHostMember) {
                throw new \RuntimeException('New host must be an active member');
            }

            $this->assignHost($room, $newHostUserId);
            event(new RoomHostChanged($room, User::find($newHostUserId)));
        });
    }

    /**
     * Set room host and update member roles: new host = host, old host = audience (listener).
     */
    private function assignHost(Room $room, int $newHostUserId): void
    {
        $oldHostId = $room->host_id;

        RoomMember::where('room_id', $room->id)
            ->where('user_id', $oldHostId)
            ->where('is_active', true)
            ->update(['role' => RoomMember::ROLE_LISTENER]);

        RoomMember::where('room_id', $room->id)
            ->where('user_id', $newHostUserId)
            ->where('is_active', true)
            ->update(['role' => RoomMember::ROLE_HOST]);

        $room->host_id = $newHostUserId;
        $room->last_activity_at = now();
        $room->save();
    }
}
