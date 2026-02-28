<?php

namespace App\Http\Controllers;

use App\Events\RoomMemberJoined;
use App\Events\RoomMemberLeft;
use App\Helpers\ApiResponse;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\RoomTheme;
use App\Models\User;
use App\Services\AgoraService;
use App\Services\DestroyRoomService;
use App\Services\HostTransferService;
use App\Services\LevelService;
use App\Services\RoomService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function __construct(
        private AgoraService $agoraService,
        private RoomService $roomService,
        private HostTransferService $hostTransfer,
        private DestroyRoomService $destroyRoom,
        private LevelService $levelService
    ) {}

    /**
     * GET /api/v1/rooms/themes – list active room themes (dynamic from admin).
     */
    public function themes()
    {
        $themes = RoomTheme::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'media_url']);

        return ApiResponse::success(
            $themes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => $t->type,
                'media_url' => $t->media_url,
            ])->values()->all()
        );
    }

    /**
     * Create a new room.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'max_seats' => 'nullable|integer|min:1|max:20',
                'settings' => 'nullable|array',
                'settings.allow_video' => 'nullable|boolean',
                'settings.allow_gifts' => 'nullable|boolean',
                'settings.allow_games' => 'nullable|boolean',
                'cover_image_url' => 'nullable|url|max:500',
                'description' => 'nullable|string|max:1000',
                'tags' => 'nullable|array',
                'allowed_gender' => 'nullable|string|in:Male,Female',
                'allowed_country' => 'nullable|string|max:100',
                'min_age' => 'nullable|integer|min:1|max:120',
                'max_age' => 'nullable|integer|min:1|max:120',
            ]);

            $user = $request->user();

            // Generate unique Agora channel name
            $agoraChannelName = 'room_' . Str::uuid();
            while (Room::where('agora_channel_name', $agoraChannelName)->exists()) {
                $agoraChannelName = 'room_' . Str::uuid();
            }

            $room = Room::create([
                'title' => $validated['title'],
                'owner_id' => $user->id,
                'host_id' => $user->id,
                'agora_channel_name' => $agoraChannelName,
                'max_seats' => $validated['max_seats'] ?? 8,
                'settings' => $validated['settings'] ?? [],
                'cover_image_url' => $validated['cover_image_url'] ?? null,
                'description' => $validated['description'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'allowed_gender' => $validated['allowed_gender'] ?? null,
                'allowed_country' => $validated['allowed_country'] ?? null,
                'min_age' => $validated['min_age'] ?? null,
                'max_age' => $validated['max_age'] ?? null,
                'is_live' => true,
                'status' => Room::STATUS_ACTIVE,
                'last_activity_at' => now(),
            ]);

            $room->display_id = $this->generateUniqueRoomDisplayId();
            $room->save();

            RoomMember::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'role' => RoomMember::ROLE_HOST,
                'joined_at' => now(),
                'is_active' => true,
            ]);

            for ($i = 0; $i < $room->max_seats; $i++) {
                \App\Models\Seat::create([
                    'room_id' => $room->id,
                    'seat_index' => $i,
                    'user_id' => null,
                    'is_muted' => false,
                    'is_locked' => false,
                ]);
            }

            return ApiResponse::success($room->load('owner'));
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('ROOM_CREATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Get a single room. Lookup by UUID (id) or by short display_id.
     */
    public function show(Request $request, string $roomId)
    {
        $room = Room::with(['owner', 'host', 'seats.user', 'theme'])
            ->where(function ($q) use ($roomId) {
                $q->where('id', $roomId)->orWhere('display_id', $roomId);
            })
            ->first();

        if (!$room || $room->trashed()) {
            return ApiResponse::notFound('Room not found');
        }

        $activeMembersCount = $room->activeMembers()->count();
        $seats = $room->seats->map(function ($seat) {
            return [
                'seat_index' => $seat->seat_index,
                'user_id' => $seat->user_id,
                'display_name' => $seat->user ? $seat->user->display_name : null,
                'avatar_url' => $seat->user ? $seat->user->avatar_url : null,
                'is_muted' => $seat->is_muted,
                'is_locked' => $seat->is_locked ?? false,
            ];
        });

        $roomData = $room->toArray();
        $roomData['members_count'] = $activeMembersCount;
        $roomData['current_seats'] = $seats;
        $roomData['host'] = $this->formatHostForResponse($room->getCurrentHostUser());
        $roomData['theme'] = $room->theme ? [
            'id' => $room->theme->id,
            'name' => $room->theme->name,
            'type' => $room->theme->type,
            'media_url' => $room->theme->media_url,
        ] : null;

        return ApiResponse::success($roomData);
    }

    /**
     * List rooms (Hot Parties / Discovery).
     * Optional filters: country, owner_id, following (1), friends (1).
     * Meta: total, current_page, last_page, limit for infinite scroll.
     */
    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);
        $sort = $request->query('sort', 'recent'); // popular or recent
        $country = $request->query('country');
        $ownerId = $request->query('owner_id');
        $following = $request->boolean('following');
        $friends = $request->boolean('friends');
        $user = $request->user();

        $query = Room::with(['owner', 'host'])
            ->where('is_live', true)
            ->where('status', Room::STATUS_ACTIVE)
            ->whereNull('deleted_at');

        if ($country !== null && $country !== '') {
            if (strtolower(trim($country)) === 'other') {
                $countryIds = \App\Models\Country::pluck('id')->all();
                $query->whereHas('owner', function ($q) use ($countryIds) {
                    $q->where(function ($q2) use ($countryIds) {
                        $q2->whereNotIn('country', $countryIds)
                            ->orWhereRaw('LOWER(TRIM(country)) = ?', ['other']);
                    });
                });
            } else {
                $query->whereHas('owner', function ($q) use ($country) {
                    $q->where('country', $country);
                });
            }
        }

        if ($ownerId !== null && $ownerId !== '') {
            $query->where('owner_id', (int) $ownerId);
        }

        if ($following) {
            $followingIds = \App\Models\UserFollower::where('follower_id', $user->id)
                ->where('status', \App\Models\UserFollower::STATUS_ACCEPTED)
                ->pluck('following_id');
            $query->whereIn('owner_id', $followingIds);
        }

        if ($friends) {
            $friendIds = $user->friends()->pluck('id');
            $query->whereIn('owner_id', $friendIds);
        }

        if ($sort === 'popular') {
            $query->withCount('activeMembers')
                ->orderBy('active_members_count', 'desc')
                ->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $total = $query->count();
        $rooms = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'display_id' => $room->display_id ?? (string) $room->display_id,
                    'title' => $room->title,
                    'owner' => [
                        'id' => $room->owner->id,
                        'display_name' => $room->owner->display_name,
                        'avatar_url' => $room->owner->avatar_url,
                    ],
                    'host' => $room->host ? [
                        'id' => $room->host->id,
                        'display_name' => $room->host->display_name,
                        'avatar_url' => $room->host->avatar_url,
                    ] : null,
                    'members_count' => $room->activeMembers()->count(),
                    'max_seats' => $room->max_seats,
                    'is_live' => $room->is_live,
                    'cover_image_url' => $room->cover_image_url,
                    'created_at' => $room->created_at,
                ];
            });

        return ApiResponse::success($rooms, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * Update a room (owner only).
     */
    public function update(Request $request, string $roomId)
    {
        try {
            $room = Room::find($roomId);

            if (!$room || $room->trashed()) {
                return ApiResponse::notFound('Room not found');
            }

        if (!$this->roomService->isHost($room, $request->user()->id)) {
            return ApiResponse::forbidden('Only room host can update the room');
        }

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'max_seats' => 'nullable|integer|min:1|max:20',
                'settings' => 'nullable|array',
                'settings.allow_video' => 'nullable|boolean',
                'settings.allow_gifts' => 'nullable|boolean',
                'settings.allow_games' => 'nullable|boolean',
                'cover_image_url' => 'nullable|url|max:500',
                'theme_id' => 'nullable|integer|exists:room_themes,id',
                'allowed_gender' => 'nullable|string|in:Male,Female',
                'allowed_country' => 'nullable|string|max:100',
                'min_age' => 'nullable|integer|min:1|max:120',
                'max_age' => 'nullable|integer|min:1|max:120',
            ]);

            $room->fill($validated);
            $room->save();

            if (isset($validated['max_seats'])) {
                $currentSeats = $room->seats()->count();
                $target = (int) $room->max_seats;
                if ($target > $currentSeats) {
                    for ($i = $currentSeats; $i < $target; $i++) {
                        \App\Models\Seat::create([
                            'room_id' => $room->id,
                            'seat_index' => $i,
                            'user_id' => null,
                            'is_muted' => false,
                            'is_locked' => false,
                        ]);
                    }
                } elseif ($target < $currentSeats) {
                    $room->seats()->where('seat_index', '>=', $target)->delete();
                }
            }

            return ApiResponse::success($room->load('owner'));
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('ROOM_UPDATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Delete/close a room (host only). Uses DestroyRoomService.
     */
    public function destroy(Request $request, string $roomId)
    {
        $room = Room::find($roomId);

        if (!$room || $room->trashed()) {
            return ApiResponse::notFound('Room not found');
        }

        if (!$this->roomService->isHost($room, $request->user()->id)) {
            return ApiResponse::forbidden('Only room host can close the room');
        }

        $this->destroyRoom->endRoom($room);
        $room->delete();

        return ApiResponse::success(['message' => 'Room closed successfully']);
    }

    /**
     * Join a room.
     */
    public function join(Request $request, string $roomId)
    {
        try {
            $room = Room::findByIdOrDisplayId($roomId);

            if (!$room || $room->trashed() || !$room->isActive()) {
                return ApiResponse::notFound('Room not found or closed');
            }

            $user = $request->user();
            $this->roomService->updateLastActivity($room);

            $existingMember = RoomMember::where('room_id', $room->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($existingMember) {
                $agoraUid = $this->agoraService->generateUid();
                $agoraToken = $this->agoraService->generateRtcToken($room, $agoraUid);
                $roomPayload = $this->roomWithHost($room->load(['owner', 'host', 'theme']));
                return ApiResponse::success([
                    'room' => $roomPayload,
                    'member' => $this->formatMemberForJoinResponse($room, $existingMember),
                    'agora_token' => $agoraToken,
                    'agora_uid' => $agoraUid,
                ]);
            }

            // Eligibility: host restrictions (gender, country, age)
            $eligibilityError = $this->checkRoomEligibility($room, $user);
            if ($eligibilityError !== null) {
                return ApiResponse::error('NOT_ELIGIBLE', $eligibilityError, 403);
            }

            // Only one host: owner joins as host only if they are (or become) the current host; else audience.
            $isOwner = (int) $user->id === (int) $room->owner_id;
            $isCurrentHost = (int) $room->host_id === (int) $user->id;
            $role = ($isOwner && ($room->host_id === null || $isCurrentHost))
                ? RoomMember::ROLE_HOST
                : RoomMember::ROLE_LISTENER;
            $member = RoomMember::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
                'is_active' => true,
            ]);

            event(new RoomMemberJoined($room, $member));

            // XP: award user for joining a room (activity)
            $this->levelService->addXp($user->id, 1);

            $agoraUid = $this->agoraService->generateUid();
            $agoraToken = $this->agoraService->generateRtcToken($room, $agoraUid);
            $roomPayload = $this->roomWithHost($room->load(['owner', 'host', 'theme']));
            return ApiResponse::success([
                'room' => $roomPayload,
                'member' => $this->formatMemberForJoinResponse($room, $member),
                'agora_token' => $agoraToken,
                'agora_uid' => $agoraUid,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('JOIN_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Check if user is eligible to join the room (gender, country, age). Returns error message or null if eligible.
     */
    private function checkRoomEligibility(Room $room, User $user): ?string
    {
        $message = 'You are not eligible to join this party based on the host\'s restrictions.';

        if ($room->allowed_gender !== null && $room->allowed_gender !== '') {
            $userGender = trim((string) ($user->gender ?? ''));
            $allowed = trim((string) $room->allowed_gender);
            if (strcasecmp($userGender, $allowed) !== 0) {
                return $message;
            }
        }

        if ($room->allowed_country !== null && $room->allowed_country !== '') {
            $userCountry = trim((string) ($user->country ?? ''));
            $allowed = trim((string) $room->allowed_country);
            if (strcasecmp($userCountry, $allowed) !== 0) {
                return $message;
            }
        }

        $minAge = $room->min_age !== null ? (int) $room->min_age : null;
        $maxAge = $room->max_age !== null ? (int) $room->max_age : null;
        if ($minAge !== null || $maxAge !== null) {
            if (!$user->dob) {
                return $message;
            }
            $age = (int) Carbon::parse($user->dob)->age;
            if ($minAge !== null && $age < $minAge) {
                return $message;
            }
            if ($maxAge !== null && $age > $maxAge) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Leave a room. If host leaves, transfer to co_host/speaker or end room.
     */
    public function leave(Request $request, string $roomId)
    {
        $user = $request->user();
        $room = Room::find($roomId);

        if (!$room || $room->trashed()) {
            return ApiResponse::notFound('Room not found');
        }

        $member = RoomMember::where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$member) {
            return ApiResponse::notFound('You are not a member of this room');
        }

        $wasHost = $this->roomService->isHost($room, $user->id);

        $member->left_at = now();
        $member->is_active = false;
        $member->save();

        \App\Models\Seat::where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->update(['user_id' => null, 'is_muted' => false]);

        event(new RoomMemberLeft($room->fresh(), $member));

        if ($wasHost) {
            $transferred = $this->hostTransfer->transferOnHostLeave($room->fresh());
            if (!$transferred) {
                $this->destroyRoom->endRoom($room->fresh());
            }
        } elseif ($this->roomService->shouldEndRoom($room->fresh())) {
            $this->destroyRoom->endRoom($room->fresh());
        }

        return ApiResponse::success(['message' => 'Left room successfully']);
    }

    /**
     * Transfer host to another user (current host only). Old host is demoted to audience (listener).
     * Body: new_host_user_id (or user_id for backward compatibility).
     */
    public function transferHost(Request $request, string $roomId)
    {
        try {
            $validated = $request->validate([
                'new_host_user_id' => 'nullable|integer',
                'user_id' => 'nullable|integer',
            ]);
            $newHostUserId = (int) ($validated['new_host_user_id'] ?? $validated['user_id'] ?? 0);
            if ($newHostUserId < 1) {
                return ApiResponse::validationError('Validation failed', ['new_host_user_id' => ['The new host user id is required.']]);
            }

            $room = Room::find($roomId);
            if (!$room || $room->trashed() || !$room->isActive()) {
                return ApiResponse::notFound('Room not found or closed');
            }

            $caller = $request->user();
            if (!$this->roomService->isHost($room, $caller->id)) {
                return ApiResponse::forbidden('Only current host can transfer host');
            }
            if ($newHostUserId === $caller->id) {
                return ApiResponse::success(['message' => 'You are already the host']);
            }

            try {
                $this->hostTransfer->manualTransfer($room, $caller->id, $newHostUserId);
            } catch (\RuntimeException $e) {
                return ApiResponse::error('TRANSFER_HOST_FAILED', $e->getMessage(), 400);
            }

            $room->refresh();
            $newHostUser = User::find($newHostUserId);
            return ApiResponse::success([
                'message' => 'Host transferred successfully',
                'host' => [
                    'id' => $newHostUser->id,
                    'display_name' => $newHostUser->display_name,
                    'avatar_url' => $newHostUser->avatar_url,
                ],
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('TRANSFER_HOST_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Get Agora token for a room.
     */
    public function getToken(Request $request, string $roomId)
    {
        try {
            $validated = $request->validate([
                'uid' => 'nullable|integer',
            ]);

            $room = Room::find($roomId);

            if (!$room || $room->trashed() || !$room->isActive()) {
                return ApiResponse::notFound('Room not found or closed');
            }

            $user = $request->user();

            $member = RoomMember::where('room_id', $roomId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$member) {
                return ApiResponse::forbidden('You must join the room first');
            }

            $agoraUid = $validated['uid'] ?? $this->agoraService->generateUid();
            $agoraToken = $this->agoraService->generateRtcToken($room, $agoraUid);

            return ApiResponse::success([
                'agora_token' => $agoraToken,
                'agora_uid' => $agoraUid,
                'expires_in' => config('services.agora.token_expiry', 3600),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('TOKEN_GENERATION_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Generate a unique 6-digit (or 8-digit fallback) display_id for rooms.
     */
    private function generateUniqueRoomDisplayId(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $id = (string) random_int(100000, 999999);
            if (!Room::where('display_id', $id)->exists()) {
                return $id;
            }
        }
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $id = (string) random_int(10000000, 99999999);
            if (!Room::where('display_id', $id)->exists()) {
                return $id;
            }
        }
        return (string) random_int(100000, 999999) . substr(uniqid(), -2);
    }

    /**
     * Format current host user for API response.
     */
    private function formatHostForResponse(?User $user): ?array
    {
        if (!$user) {
            return null;
        }
        return [
            'id' => $user->id,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_url,
        ];
    }

    /**
     * Room payload with owner, host, and theme for join/state response.
     * Includes active theme (id, name, type, media_url) so clients (e.g. late joiners) see current background.
     */
    private function roomWithHost(Room $room): array
    {
        $data = $room->toArray();
        $data['host'] = $this->formatHostForResponse($room->getCurrentHostUser());
        $data['theme'] = $room->theme ? [
            'id' => $room->theme->id,
            'name' => $room->theme->name,
            'type' => $room->theme->type,
            'media_url' => $room->theme->media_url,
        ] : null;
        return $data;
    }

    /**
     * Format member for join response: role is "host" only for current host (room.host_id); else actual role (listener/speaker/co_host).
     */
    private function formatMemberForJoinResponse(Room $room, RoomMember $member): array
    {
        $role = (int) $member->user_id === (int) $room->host_id ? 'host' : $member->role;
        return [
            'id' => $member->id,
            'room_id' => $member->room_id,
            'user_id' => $member->user_id,
            'role' => $role,
            'seat_index' => $member->seat_index,
            'joined_at' => $member->joined_at,
        ];
    }
}

