<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\BlockedUser;
use App\Models\CoinTransaction;
use App\Models\Country;
use App\Models\Friendship;
use App\Models\User;
use App\Models\UserFollower;
use App\Models\VirtualGift;
use App\Models\WealthPrivilege;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserInteractionController extends Controller
{
    public function __construct(
        private FirebaseService $firebase
    ) {}
    /**
     * POST /user/follow - Body: following_id
     * If target has private_account, creates a follow request (pending); otherwise accepted immediately.
     */
    public function follow(Request $request)
    {
        try {
            $validated = $request->validate([
                'following_id' => 'nullable|integer|exists:users,id',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $followingId = (int) ($validated['following_id'] ?? $validated['user_id'] ?? 0);
        if ($followingId <= 0) {
            return ApiResponse::validationError('Validation failed', ['following_id' => ['The following_id or user_id field is required.']]);
        }
        if ($followingId === $user->id) {
            return ApiResponse::error('INVALID_REQUEST', 'Cannot follow yourself', 400);
        }
        if (BlockedUser::where('blocker_id', $user->id)->where('blocked_id', $followingId)->exists()) {
            return ApiResponse::forbidden('Cannot follow blocked user');
        }

        $target = User::find($followingId);
        $isPrivate = $target && $target->private_account === true;
        $status = $isPrivate ? UserFollower::STATUS_PENDING : UserFollower::STATUS_ACCEPTED;

        $record = UserFollower::firstOrCreate(
            ['follower_id' => $user->id, 'following_id' => $followingId],
            ['follower_id' => $user->id, 'following_id' => $followingId, 'status' => $status]
        );
        // Only set status on create; do not overwrite existing accepted with pending

        if ($status === UserFollower::STATUS_ACCEPTED && $this->firebase->isConfigured()) {
            $this->firebase->sendToUser($followingId, 'New follower', ($user->display_name ?? $user->name ?? 'Someone') . ' started following you', ['type' => 'follow', 'user_id' => (string) $user->id]);
        } elseif ($status === UserFollower::STATUS_PENDING && $this->firebase->isConfigured()) {
            $this->firebase->sendToUser($followingId, 'Follow request', ($user->display_name ?? $user->name ?? 'Someone') . ' requested to follow you', ['type' => 'follow_request', 'user_id' => (string) $user->id]);
        }

        return ApiResponse::success([
            'message' => $isPrivate ? 'Follow request sent' : 'Following',
            'follow_request_pending' => $isPrivate,
        ]);
    }

    /**
     * GET /user/follow-requests - Pending follow requests to the current user (for private accounts).
     * Returns list of { id, follower_id, name, avatar, level }.
     */
    public function followRequests(Request $request)
    {
        $user = $request->user();
        $requests = UserFollower::where('following_id', $user->id)
            ->where('status', UserFollower::STATUS_PENDING)
            ->with('follower')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (UserFollower $f) {
                $requester = $f->follower;
                return [
                    'id' => $f->id,
                    'follower_id' => $requester->id,
                    'name' => $requester->display_name ?? $requester->name ?? 'User',
                    'avatar' => $requester->avatar_url,
                    'level' => (int) ($requester->level ?? 0),
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success($requests);
    }

    /**
     * POST /user/accept-follow-request - Body: follower_id (accept a pending follow request).
     * Only the account that was requested (following_id) can accept.
     */
    public function acceptFollowRequest(Request $request)
    {
        try {
            $validated = $request->validate(['follower_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $followerId = (int) $validated['follower_id'];
        $record = UserFollower::where('follower_id', $followerId)
            ->where('following_id', $user->id)
            ->where('status', UserFollower::STATUS_PENDING)
            ->first();
        if (!$record) {
            return ApiResponse::notFound('Follow request not found or already handled');
        }
        $record->update(['status' => UserFollower::STATUS_ACCEPTED]);
        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($followerId, 'Follow request accepted', ($user->display_name ?? $user->name ?? 'Someone') . ' accepted your follow request', ['type' => 'follow_accepted', 'user_id' => (string) $user->id]);
        }
        return ApiResponse::success(['message' => 'Follow request accepted']);
    }

    /**
     * POST /user/reject-follow-request - Body: follower_id (reject a pending follow request).
     */
    public function rejectFollowRequest(Request $request)
    {
        try {
            $validated = $request->validate(['follower_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $followerId = (int) $validated['follower_id'];
        $deleted = UserFollower::where('follower_id', $followerId)
            ->where('following_id', $user->id)
            ->where('status', UserFollower::STATUS_PENDING)
            ->delete();
        if (!$deleted) {
            return ApiResponse::notFound('Follow request not found or already handled');
        }
        return ApiResponse::success(['message' => 'Follow request rejected']);
    }

    /**
     * POST /user/unfollow - Body: following_id or user_id
     */
    public function unfollow(Request $request)
    {
        try {
            $validated = $request->validate([
                'following_id' => 'nullable|integer|exists:users,id',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $followingId = (int) ($validated['following_id'] ?? $validated['user_id'] ?? 0);
        if ($followingId <= 0) {
            return ApiResponse::validationError('Validation failed', ['following_id' => ['The following_id or user_id field is required.']]);
        }
        $user = $request->user();
        UserFollower::where('follower_id', $user->id)->where('following_id', $followingId)->delete();
        return ApiResponse::success(['message' => 'Unfollowed']);
    }

    /**
     * POST /api/v1/user/add-friend - Body: target_id (user ID to add as friend).
     * Auth: Bearer token required.
     * Creates a pending friend request (or auto-accept per receiver privacy config).
     */
    public function addFriend(Request $request)
    {
        try {
            $request->validate([
                'target_id' => 'required_without:friend_id|integer|exists:users,id',
                'friend_id' => 'required_without:target_id|integer|exists:users,id', // backward compat
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or request already pending.',
            ], 400);
        }
        $targetId = (int) ($request->input('target_id') ?? $request->input('friend_id'));
        $user = $request->user();

        $target = User::find($targetId);
        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or request already pending.',
            ], 404);
        }

        if ($targetId === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or request already pending.',
            ], 400);
        }
        if (BlockedUser::where('blocker_id', $user->id)->where('blocked_id', $targetId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or request already pending.',
            ], 400);
        }

        $existing = Friendship::where(function ($q) use ($user, $targetId) {
            $q->where('user_id', $user->id)->where('friend_id', $targetId);
        })->orWhere(function ($q) use ($user, $targetId) {
            $q->where('user_id', $targetId)->where('friend_id', $user->id);
        })->first();

        if ($existing) {
            if ($existing->status === Friendship::STATUS_ACCEPTED) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found or request already pending.',
                ], 400);
            }
            return response()->json([
                'success' => false,
                'message' => 'User not found or request already pending.',
            ], 400);
        }

        Friendship::create([
            'user_id' => $user->id,
            'friend_id' => $targetId,
            'status' => Friendship::STATUS_PENDING,
        ]);
        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($targetId, 'Friend request', ($user->display_name ?? $user->name ?? 'Someone') . ' sent you a friend request', ['type' => 'friend_request', 'user_id' => (string) $user->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Friend request sent successfully.',
        ], 200);
    }

    /**
     * POST /user/friend-request - Body: user_id (alias for add-friend). Send friend request.
     */
    public function sendFriendRequest(Request $request)
    {
        $request->merge([
            'target_id' => $request->input('user_id'),
        ]);
        return $this->addFriend($request);
    }

    /**
     * POST /user/friend-request/accept - Body: request_id (Friendship id) or user_id. Accept friend request.
     */
    public function acceptFriendRequestByRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_id' => 'nullable|integer|exists:friendships,id',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $requestRow = null;
        if (!empty($validated['request_id'])) {
            $requestRow = Friendship::where('id', $validated['request_id'])
                ->where('friend_id', $user->id)
                ->where('status', Friendship::STATUS_PENDING)
                ->first();
        } elseif (!empty($validated['user_id'])) {
            $requestRow = Friendship::where('user_id', $validated['user_id'])
                ->where('friend_id', $user->id)
                ->where('status', Friendship::STATUS_PENDING)
                ->first();
        }
        if (!$requestRow) {
            return ApiResponse::notFound('Friend request not found');
        }
        $friendId = $requestRow->user_id;
        $requestRow->update(['status' => Friendship::STATUS_ACCEPTED]);
        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($friendId, 'Friend request accepted', ($user->display_name ?? $user->name ?? 'Someone') . ' accepted your friend request', ['type' => 'friend_accepted', 'user_id' => (string) $user->id]);
        }
        return ApiResponse::success(['message' => 'Friend request accepted']);
    }

    /**
     * POST /user/friend-request/decline - Body: user_id. Decline incoming or cancel outgoing friend request.
     */
    public function declineFriendRequest(Request $request)
    {
        try {
            $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $otherId = (int) $validated['user_id'];
        $deleted = Friendship::where('user_id', $otherId)
            ->where('friend_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->delete();
        if (!$deleted) {
            $deleted = Friendship::where('user_id', $user->id)
                ->where('friend_id', $otherId)
                ->where('status', Friendship::STATUS_PENDING)
                ->delete();
        }
        if (!$deleted) {
            return ApiResponse::notFound('Friend request not found or already handled');
        }
        return ApiResponse::success(['message' => 'Friend request declined']);
    }

    /**
     * GET /user/friend-requests - Pending friend requests to the current user.
     * Returns list of { id, user_id, name, avatar, level } (requester = user_id).
     */
    public function friendRequests(Request $request)
    {
        $user = $request->user();
        $requests = Friendship::where('friend_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Friendship $f) {
                $requester = $f->user;
                return [
                    'id' => $f->id,
                    'user_id' => $requester->id,
                    'name' => $requester->display_name ?? $requester->name ?? 'User',
                    'avatar' => $requester->avatar_url,
                    'level' => (int) ($requester->level ?? 0),
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success($requests);
    }

    /**
     * POST /user/accept-friend - Body: friend_id (accept incoming request)
     */
    public function acceptFriend(Request $request)
    {
        try {
            $validated = $request->validate(['friend_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $friendId = (int) $validated['friend_id'];
        $requestRow = Friendship::where('user_id', $friendId)->where('friend_id', $user->id)->where('status', Friendship::STATUS_PENDING)->first();
        if (!$requestRow) {
            return ApiResponse::notFound('Friend request not found');
        }
        $requestRow->update(['status' => Friendship::STATUS_ACCEPTED]);
        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($friendId, 'Friend request accepted', ($user->display_name ?? $user->name ?? 'Someone') . ' accepted your friend request', ['type' => 'friend_accepted', 'user_id' => (string) $user->id]);
        }
        return ApiResponse::success(['message' => 'Friend request accepted']);
    }

    /**
     * POST /user/reject-friend - Body: friend_id (decline incoming request)
     */
    public function rejectFriend(Request $request)
    {
        try {
            $validated = $request->validate(['friend_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $friendId = (int) $validated['friend_id'];
        $deleted = Friendship::where('user_id', $friendId)
            ->where('friend_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->delete();

        if (!$deleted) {
            return ApiResponse::notFound('Friend request not found or already handled');
        }
        return ApiResponse::success(['message' => 'Friend request declined']);
    }

    /**
     * POST /user/block - Body: blocked_user_id
     */
    public function block(Request $request)
    {
        try {
            $validated = $request->validate(['blocked_user_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $blockedId = (int) $validated['blocked_user_id'];
        if ($blockedId === $user->id) {
            return ApiResponse::error('INVALID_REQUEST', 'Cannot block yourself', 400);
        }
        BlockedUser::firstOrCreate(
            ['blocker_id' => $user->id, 'blocked_id' => $blockedId],
            ['blocker_id' => $user->id, 'blocked_id' => $blockedId]
        );
        return ApiResponse::success(['message' => 'User blocked']);
    }

    /**
     * GET /users/{id}/followers - List of users following this user (ContactDto).
     */
    public function followers(Request $request, $id)
    {
        $viewer = $request->user();
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }
        $records = UserFollower::where('following_id', $id)
            ->where('status', UserFollower::STATUS_ACCEPTED)
            ->with('follower')
            ->orderBy('created_at', 'desc')
            ->get();
        $list = $records->map(function (UserFollower $r) use ($viewer) {
            $u = $r->follower;
            if (!$u) {
                return null;
            }
            $online = User::getOnlineStatusForViewer($u, (int) $viewer->id);
            return [
                'id' => $u->id,
                'name' => $u->display_name ?? $u->name ?? 'User',
                'avatar_url' => $u->avatar_url,
                'is_online' => $online['is_online'],
                'last_seen_at' => $online['last_seen_at'],
            ];
        })->filter()->values()->all();
        return ApiResponse::success($list);
    }

    /**
     * GET /users/{id}/following - List of users this user is following (ContactDto).
     */
    public function following(Request $request, $id)
    {
        $viewer = $request->user();
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }
        $records = UserFollower::where('follower_id', $id)
            ->where('status', UserFollower::STATUS_ACCEPTED)
            ->with('following')
            ->orderBy('created_at', 'desc')
            ->get();
        $list = $records->map(function (UserFollower $r) use ($viewer) {
            $u = $r->following;
            if (!$u) {
                return null;
            }
            $online = User::getOnlineStatusForViewer($u, (int) $viewer->id);
            return [
                'id' => $u->id,
                'name' => $u->display_name ?? $u->name ?? 'User',
                'avatar_url' => $u->avatar_url,
                'is_online' => $online['is_online'],
                'last_seen_at' => $online['last_seen_at'],
            ];
        })->filter()->values()->all();
        return ApiResponse::success($list);
    }

    /**
     * GET /users/{id} or GET /user/{id} - Public profile by database primary key (integer).
     * Use the user's DB id (e.g. 5), not Agora UID or any other identifier.
     */
    public function show(Request $request, $id)
    {
        $me = $request->user();
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }
        $isBlocked = BlockedUser::where('blocker_id', $me->id)->where('blocked_id', $target->id)->exists();
        $hasBlockedMe = BlockedUser::where('blocker_id', $target->id)->where('blocked_id', $me->id)->exists();
        $followRecord = UserFollower::where('follower_id', $me->id)->where('following_id', $target->id)->first();
        $isFollowing = $followRecord && $followRecord->status === UserFollower::STATUS_ACCEPTED;
        $followRequestPending = $followRecord && $followRecord->status === UserFollower::STATUS_PENDING;
        $isConfirmedFollower = $isFollowing;
        $followersCount = UserFollower::where('following_id', $target->id)->where('status', UserFollower::STATUS_ACCEPTED)->count();
        $followingCount = UserFollower::where('follower_id', $target->id)->where('status', UserFollower::STATUS_ACCEPTED)->count();
        $friendsCount = Friendship::where(function ($q) use ($target) {
            $q->where('user_id', $target->id)->orWhere('friend_id', $target->id);
        })->where('status', Friendship::STATUS_ACCEPTED)->count();
        $isFriend = Friendship::where(function ($q) use ($me, $target) {
            $q->where('user_id', $me->id)->where('friend_id', $target->id);
        })->orWhere(function ($q) use ($me, $target) {
            $q->where('user_id', $target->id)->where('friend_id', $me->id);
        })->where('status', Friendship::STATUS_ACCEPTED)->exists();

        $friendRequestStatus = 'none';
        if (!$isFriend) {
            $sentPending = Friendship::where('user_id', $me->id)->where('friend_id', $target->id)->where('status', Friendship::STATUS_PENDING)->exists();
            $receivedPending = Friendship::where('user_id', $target->id)->where('friend_id', $me->id)->where('status', Friendship::STATUS_PENDING)->exists();
            if ($sentPending) {
                $friendRequestStatus = 'sent';
            } elseif ($receivedPending) {
                $friendRequestStatus = 'received';
            }
        }

        if (!$target->country || strtolower(trim($target->country)) === 'other') {
            $countryName = $target->country ? 'Other' : null;
            $countryFlag = null;
        } else {
            $countryRow = Country::find($target->country);
            $countryName = $countryRow ? $countryRow->name : $target->country;
            $countryFlag = $countryRow?->flag_url;
        }

        $onlineForViewer = User::getOnlineStatusForViewer($target, (int) $me->id);

        // Private account: hide followers/following counts and sensitive data if requester is not a confirmed follower
        $hidePrivateCounts = $target->private_account === true && !$isConfirmedFollower;
        $followersCountForResponse = $hidePrivateCounts ? null : $followersCount;
        $followingCountForResponse = $hidePrivateCounts ? null : $followingCount;

        // Contract: data.id must match the {id} in the URL (database primary key). UserProfileDto with block flags.
        $data = [
            'id' => (int) $target->id,
            'name' => $target->display_name ?? $target->name ?? 'User',
            'avatar' => $target->avatar_url,
            'level' => (int) ($target->level ?? 0),
            'country' => $countryName,
            'country_flag' => $countryFlag,
            'gender' => $target->gender,
            'followers_count' => $followersCountForResponse,
            'following_count' => $followingCountForResponse,
            'friends_count' => $friendsCount,
            'is_following' => $isFollowing,
            'follow_request_pending' => $followRequestPending ?? false,
            'is_friend' => $isFriend,
            'friend_request_status' => $friendRequestStatus,
            'is_blocked' => $isBlocked,
            'has_blocked_me' => $hasBlockedMe,
            'is_online' => $onlineForViewer['is_online'],
            'last_seen_at' => $onlineForViewer['last_seen_at'],
            'private_account' => (bool) $target->private_account,
            'show_online_status' => (bool) $target->show_online_status,
        ];
        return ApiResponse::success($data);
    }

    /**
     * GET /users/{id}/gifts - Gift gallery: gifts received by this user (Charm tab).
     */
    public function gifts(Request $request, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $user = User::find($id);
        if (!$user) {
            return ApiResponse::notFound('User not found');
        }

        $received = CoinTransaction::where('receiver_id', $id)
            ->where('transaction_type', CoinTransaction::TYPE_GIFT)
            ->whereNotNull('reference_id')
            ->selectRaw('reference_id as gift_id, count(*) as count_received')
            ->groupBy('reference_id')
            ->get();

        $giftIds = $received->pluck('gift_id')->unique()->filter()->values()->all();
        $giftsById = VirtualGift::whereIn('id', $giftIds)->get()->keyBy('id');
        $countByGift = $received->keyBy('gift_id');

        $gifts = $received->map(function ($row) use ($giftsById, $countByGift) {
            $gift = $giftsById->get($row->gift_id);
            if (!$gift) {
                return null;
            }
            return [
                'id' => $gift->id,
                'name' => $gift->name,
                'icon_url' => $gift->image_url,
                'count_received' => (int) $row->count_received,
                'rarity' => $gift->rarity ?? 'common',
            ];
        })->filter()->values()->all();

        $totalGiftsCollected = (int) CoinTransaction::where('receiver_id', $id)
            ->where('transaction_type', CoinTransaction::TYPE_GIFT)
            ->whereNotNull('reference_id')
            ->selectRaw('count(distinct reference_id) as c')
            ->value('c');
        $maxGiftsAvailable = VirtualGift::where('is_active', true)->count();

        return ApiResponse::success([
            'total_gifts_collected' => $totalGiftsCollected,
            'max_gifts_available' => $maxGiftsAvailable,
            'gifts' => $gifts,
        ]);
    }

    /**
     * GET /users/{id}/privileges - Wealth privileges list (Wealth Dashboard tab).
     */
    public function privileges(Request $request, $id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $user = User::find($id);
        if (!$user) {
            return ApiResponse::notFound('User not found');
        }

        $userLevel = (int) ($user->level ?? 0);
        $privileges = WealthPrivilege::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'title' => $p->title,
                'description' => $p->description,
                'icon_identifier' => $p->icon_identifier,
                'is_unlocked' => $userLevel >= (int) $p->level_required,
            ])
            ->values()
            ->all();

        return ApiResponse::success($privileges);
    }
}
