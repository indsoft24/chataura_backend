<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Services\DestroyRoomService;
use App\Services\RoomService;
use Illuminate\Console\Command;

/**
 * Every 2 minutes: end rooms where status=active and (no active host AND no active speaker/co_host).
 */
class CleanupEmptyRoomsCommand extends Command
{
    protected $signature = 'rooms:cleanup-empty';

    protected $description = 'End rooms with no active host and no active speaker/co_host';

    public function __construct(
        private RoomService $roomService,
        private DestroyRoomService $destroyRoomService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $rooms = Room::where('status', Room::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->get();

        $ended = 0;
        foreach ($rooms as $room) {
            if ($this->roomService->shouldEndRoom($room)) {
                $this->destroyRoomService->endRoom($room);
                $ended++;
            }
        }

        if ($ended > 0) {
            $this->info("Ended {$ended} empty room(s).");
        }

        return self::SUCCESS;
    }
}
