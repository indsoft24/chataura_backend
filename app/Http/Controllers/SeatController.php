<?php

namespace App\Http\Controllers;

use App\Events\RoomSeatUpdated;
use App\Helpers\ApiResponse;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Seat;
use App\Models\User;
use App\Services\RoomService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeatController extends Controller
{
    public function __construct(
        private RoomService $roomService
    ) {}
    /**
     * List seats in a room.
     */
    public function index(Request $request, string $roomId)
    {
        $room = Room::find($roomId);

        if (!$room || $room->trashed()) {
            return ApiResponse::notFound('Room not found');
        }

        $seats = Seat::where('room_id', $roomId)
            ->with('user')
            ->orderBy('seat_index')
            ->get()
            ->map(function ($seat) {
                return [
                    'seat_index' => $seat->seat_index,
                    'user_id' => $seat->user_id,
                    'display_name' => $seat->user ? $seat->user->display_name : null,
                    'avatar_url' => $seat->user ? $seat->user->avatar_url : null,
                    'is_muted' => $seat->is_muted,
                    'is_locked' => $seat->is_locked,
                ];
            });

        return ApiResponse::success([
            'seats' => $seats,
            'max_seats' => $room->max_seats,
        ]);
    }

    /**
     * Take a seat. Only host or co-host can call this to take a seat directly.
     * Audience members request a seat via in-room chat (CMD:REQ); the host approves via assign().
     */
    public function take(Request $request, string $roomId, int $seatIndex)
    {
        try {
            $room = Room::find($roomId);

            if (!$room || $room->trashed() || !$room->isActive()) {
                return ApiResponse::notFound('Room not found or closed');
            }

            if ($seatIndex < 0 || $seatIndex >= $room->max_seats) {
                return ApiResponse::error('INVALID_SEAT', 'Invalid seat index', 400);
            }

            $user = $request->user();

            $member = RoomMember::where('room_id', $roomId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$member) {
                return ApiResponse::forbidden('You must join the room first');
            }

            if (!$this->roomService->isHost($room, $user->id) && $member->role !== RoomMember::ROLE_CO_HOST) {
                return ApiResponse::forbidden('Only host or co-host can take a seat directly. Otherwise the host must assign you.');
            }

            $seat = Seat::where('room_id', $roomId)
                ->where('seat_index', $seatIndex)
                ->first();

            if (!$seat) {
                return ApiResponse::notFound('Seat not found');
            }

            if ($seat->user_id && $seat->user_id !== $user->id) {
                return ApiResponse::conflict('Seat is already taken');
            }

            // Free user's current seat if any
            Seat::where('room_id', $roomId)
                ->where('user_id', $user->id)
                ->update(['user_id' => null, 'is_muted' => false]);

            $seat->user_id = $user->id;
            $seat->is_muted = false;
            $seat->last_heartbeat_at = now();
            $seat->save();

            $member->seat_index = $seatIndex;
            $member->role = RoomMember::ROLE_SPEAKER;
            $member->save();

            event(new RoomSeatUpdated($room, $seat->fresh()));

            return ApiResponse::success([
                'seat_index' => $seat->seat_index,
                'user_id' => $seat->user_id,
                'is_muted' => $seat->is_muted,
                'is_locked' => $seat->is_locked,
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('TAKE_SEAT_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Assign a seat to a user. Only the current host can assign; full control over who gets a seat.
     */
    public function assign(Request $request, string $roomId, int $seatIndex)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
            ]);

            $room = Room::find($roomId);
            if (!$room || $room->trashed() || !$room->isActive()) {
                return ApiResponse::notFound('Room not found or closed');
            }

            if ($seatIndex < 0 || $seatIndex >= $room->max_seats) {
                return ApiResponse::error('INVALID_SEAT', 'Invalid seat index', 400);
            }

            $user = $request->user();

            if (!$this->roomService->isHost($room, $user->id)) {
                return ApiResponse::forbidden('Only host can assign seats');
            }

            $targetUserId = $validated['user_id'];

            if (!User::where('id', $targetUserId)->exists()) {
                return ApiResponse::error('USER_NOT_FOUND', 'User not found. The user_id must be an existing user.', 400);
            }

            $targetMember = RoomMember::where('room_id', $roomId)
                ->where('user_id', $targetUserId)
                ->where('is_active', true)
                ->first();

            if (!$targetMember) {
                $targetMember = new RoomMember([
                    'room_id' => $roomId,
                    'user_id' => $targetUserId,
                    'role' => RoomMember::ROLE_LISTENER,
                    'joined_at' => now(),
                    'is_active' => true,
                ]);
                $targetMember->save();
            }

            $seat = Seat::where('room_id', $roomId)
                ->where('seat_index', $seatIndex)
                ->first();

            if (!$seat) {
                return ApiResponse::notFound('Seat not found');
            }

            // Free target user's current seat if any
            Seat::where('room_id', $roomId)
                ->where('user_id', $targetUserId)
                ->update(['user_id' => null, 'is_muted' => false]);

            $seat->user_id = $targetUserId;
            $seat->is_muted = false;
            $seat->last_heartbeat_at = now();
            $seat->save();

            $targetMember->seat_index = $seatIndex;
            $targetMember->role = RoomMember::ROLE_SPEAKER;
            $targetMember->save();

            event(new RoomSeatUpdated($room, $seat->fresh()));

            return ApiResponse::success([
                'seat_index' => $seat->seat_index,
                'user_id' => $seat->user_id,
                'is_muted' => $seat->is_muted,
                'is_locked' => $seat->is_locked,
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('ASSIGN_SEAT_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Free a seat (Host/Owner only). Removes the user from the seat and clears their seat_index.
     */
    public function free(Request $request, string $roomId, int $seatIndex)
    {
        $room = Room::find($roomId);
        if (!$room || $room->trashed() || !$room->is_live) {
            return ApiResponse::notFound('Room not found or closed');
        }

        if ($seatIndex < 0 || $seatIndex >= $room->max_seats) {
            return ApiResponse::error('INVALID_SEAT', 'Invalid seat index', 400);
        }

        $user = $request->user();

        if (!$this->roomService->isHost($room, $user->id)) {
            return ApiResponse::forbidden('Only host can free seats');
        }

        $seat = Seat::where('room_id', $roomId)
            ->where('seat_index', $seatIndex)
            ->first();

        if (!$seat) {
            return ApiResponse::notFound('Seat not found');
        }

        $evictedUserId = $seat->user_id;

        $seat->user_id = null;
        $seat->is_muted = false;
        $seat->save();

        if ($evictedUserId) {
            $member = RoomMember::where('room_id', $roomId)
                ->where('user_id', $evictedUserId)
                ->where('is_active', true)
                ->first();
            if ($member) {
                $member->seat_index = null;
                $member->role = RoomMember::ROLE_LISTENER;
                $member->save();
            }
        }

        event(new RoomSeatUpdated($room, $seat->fresh()));

        return ApiResponse::success([
            'seat_index' => $seatIndex,
            'message' => 'Seat freed successfully',
        ]);
    }

    /**
     * Leave current seat.
     */
    public function leave(Request $request, string $roomId)
    {
        $user = $request->user();

        $seat = Seat::where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->first();

        if (!$seat) {
            return ApiResponse::notFound('You are not in any seat');
        }

        $seat->user_id = null;
        $seat->is_muted = false;
        $seat->save();

        // Update room member
        $member = \App\Models\RoomMember::where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if ($member) {
            $member->seat_index = null;
            $member->role = RoomMember::ROLE_LISTENER;
            $member->save();
        }

        $room = Room::find($roomId);
        if ($room && !$room->trashed()) {
            event(new RoomSeatUpdated($room, $seat->fresh()));
        }

        return ApiResponse::success(['message' => 'Left seat successfully']);
    }

    /**
     * Mute/unmute a seat.
     */
    public function mute(Request $request, string $roomId, int $seatIndex)
    {
        try {
            $validated = $request->validate([
                'muted' => 'required|boolean',
            ]);

            $room = Room::find($roomId);

            if (!$room || $room->trashed()) {
                return ApiResponse::notFound('Room not found');
            }

            $user = $request->user();

            $seat = Seat::where('room_id', $roomId)
                ->where('seat_index', $seatIndex)
                ->first();

            if (!$seat) {
                return ApiResponse::notFound('Seat not found');
            }

            $canMute = ($seat->user_id === $user->id) || $this->roomService->isHost($room, $user->id);

            if (!$canMute) {
                return ApiResponse::forbidden('You do not have permission to mute this seat');
            }

            $seat->is_muted = $validated['muted'];
            $seat->save();

            return ApiResponse::success([
                'seat_index' => $seat->seat_index,
                'is_muted' => $seat->is_muted,
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('MUTE_FAILED', $e->getMessage(), 500);
        }
    }
}

