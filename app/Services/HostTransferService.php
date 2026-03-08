<?php

namespace App\Services;

use App\Events\RoomHostChanged;
use App\Events\RoomRoleUpdated;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Host authority: transfer on leave, manual transfer, promote to co-host. All in transaction.
 *
 * Exclusive Co-Host constraint: A room has exactly 1 host and at most 1 co-host.
 * When assigning a new host (manual or automatic), co_host_id is always cleared so the
 * room has 0 co-hosts until the new host assigns one. When promoting a new co-host,
 * the previous co-host is revoked to speaker.
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
     * Manual transfer: current user must be host. Target must be a seated member. New host gets host role; old host becomes seated member (speaker).
     */
    public function manualTransfer(Room $room, int $currentUserId, int $newHostUserId): void
    {
        DB::transaction(function () use ($room, $currentUserId, $newHostUserId) {
            if ((int) $room->host_id !== $currentUserId) {
                throw new \RuntimeException('Only current host can transfer host');
            }

            $isSeated = Seat::where('room_id', $room->id)
                ->where('user_id', $newHostUserId)
                ->exists();

            if (!$isSeated) {
                throw new \RuntimeException('Target must be a seated member');
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
     * Promote a seated member to co-host. Caller must be current host. Target must be seated. Emits room_role_updated.
     */
    public function setCoHost(Room $room, int $currentUserId, int $targetUserId): void
    {
        DB::transaction(function () use ($room, $currentUserId, $targetUserId) {
            if ((int) $room->host_id !== $currentUserId) {
                throw new \RuntimeException('Only current host can promote to co-host');
            }

            $isSeated = Seat::where('room_id', $room->id)
                ->where('user_id', $targetUserId)
                ->exists();

            if (!$isSeated) {
                throw new \RuntimeException('Target must be a seated member');
            }

            $targetMember = RoomMember::where('room_id', $room->id)
                ->where('user_id', $targetUserId)
                ->where('is_active', true)
                ->first();

            if (!$targetMember) {
                throw new \RuntimeException('Target must be an active room member');
            }

            if ((int) $targetUserId === (int) $room->host_id) {
                throw new \RuntimeException('Host is already the host');
            }

            // Exclusive co-host: only one co-host; revoke previous co-host to speaker
            if ($room->co_host_id) {
                RoomMember::where('room_id', $room->id)
                    ->where('user_id', $room->co_host_id)
                    ->where('is_active', true)
                    ->update(['role' => RoomMember::ROLE_SPEAKER]);
            }

            $room->co_host_id = $targetUserId;
            $room->last_activity_at = now();
            $room->save();

            RoomMember::where('room_id', $room->id)
                ->where('user_id', $targetUserId)
                ->where('is_active', true)
                ->update(['role' => RoomMember::ROLE_CO_HOST]);

            $targetMember->refresh();
            event(new RoomRoleUpdated($room->fresh(), $targetMember));
        });
    }

    /**
     * Set room host and update member roles: new host = host, old host = speaker (seated).
     * CRITICAL: Always clear co_host_id so the room has 0 co-hosts after migration (whether
     * the new host was the previous co-host or a speaker). New host must assign a co-host again.
     */
    private function assignHost(Room $room, int $newHostUserId): void
    {
        $oldHostId = $room->host_id;

        RoomMember::where('room_id', $room->id)
            ->where('user_id', $oldHostId)
            ->where('is_active', true)
            ->update(['role' => RoomMember::ROLE_SPEAKER]);

        RoomMember::where('room_id', $room->id)
            ->where('user_id', $newHostUserId)
            ->where('is_active', true)
            ->update(['role' => RoomMember::ROLE_HOST]);

        $room->host_id = $newHostUserId;
        $room->co_host_id = null; // Co-Host migration constraint: room has 0 co-hosts until new host assigns one
        $room->last_activity_at = now();
        $room->save();
    }
}
