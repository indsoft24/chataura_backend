<?php

namespace App\Console\Commands;

use App\Events\RoomSeatUpdated;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Seat;
use App\Services\DestroyRoomService;
use Illuminate\Console\Command;

/**
 * Room auto-termination: free seats with no heartbeat for 60+ seconds, end rooms with no seated members and stale host heartbeat.
 * Run every minute. Users who were auto-freed can re-join as new (take/assign seat again).
 */
class CleanupStaleRoomHeartbeatsCommand extends Command
{
    protected $signature = 'rooms:cleanup-stale-heartbeats';

    protected $description = 'Free seats with last_heartbeat_at older than 60s; end rooms with zero seated members and stale host heartbeat';

    private const HEARTBEAT_TIMEOUT_SECONDS = 60;

    public function __construct(
        private DestroyRoomService $destroyRoomService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = now()->subSeconds(self::HEARTBEAT_TIMEOUT_SECONDS);
        $freed = 0;
        $ended = 0;

        // 1) Free seats where the seated user's last_heartbeat_at is stale (or never set)
        $staleSeats = Seat::whereNotNull('user_id')
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $threshold);
            })
            ->with('room')
            ->get();

        foreach ($staleSeats as $seat) {
            $room = $seat->room;
            if (!$room || $room->trashed() || $room->status !== Room::STATUS_ACTIVE) {
                continue;
            }

            $evictedUserId = $seat->user_id;
            $seat->user_id = null;
            $seat->is_muted = false;
            $seat->last_heartbeat_at = null;
            $seat->save();

            $member = RoomMember::where('room_id', $room->id)
                ->where('user_id', $evictedUserId)
                ->where('is_active', true)
                ->first();
            if ($member) {
                $member->seat_index = null;
                $member->role = RoomMember::ROLE_LISTENER;
                $member->save();
            }

            event(new RoomSeatUpdated($room, $seat->fresh()));
            $freed++;
        }

        // 2) End active rooms that have zero seated members and host's last heartbeat is stale
        $activeRooms = Room::where('status', Room::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->get();

        foreach ($activeRooms as $room) {
            $seatedCount = Seat::where('room_id', $room->id)->whereNotNull('user_id')->count();
            if ($seatedCount > 0) {
                continue;
            }

            $hostStale = $room->host_last_heartbeat_at === null
                || $room->host_last_heartbeat_at->lt($threshold);

            if ($hostStale) {
                $this->destroyRoomService->endRoom($room);
                $ended++;
            }
        }

        if ($freed > 0 || $ended > 0) {
            $this->info("Freed {$freed} stale seat(s), ended {$ended} room(s).");
        }

        return self::SUCCESS;
    }
}
