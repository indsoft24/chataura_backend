<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\BlockedUser;
use App\Models\CoinTransaction;
use App\Models\Country;
use App\Models\Friendship;
use App\Models\FriendRequest;
use App\Models\MediaPost;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Models\User;
use App\Models\UserFollower;
use App\Models\VirtualGift;
use App\Models\WealthPrivilege;
use App\Services\ApiCacheService;
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
    public function follow(Request $request, ApiCacheService $cache)
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

        $this->invalidateRelationshipCaches($cache, [$user->id, $followingId], true);

        return ApiResponse::success([
            'message' => $isPrivate ? 'Follow request sent' : 'Following',
            'follow_request_pending' => $isPrivate,
        ]);
    }

    /**
     * GET /user/follow-requests - Pending follow requests. Paginated.
     * Query: page (default 1), limit (default 20, max 50).
     */
    public function followRequests(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);

        $query = UserFollower::where('following_id', $user->id)
            ->where('status', UserFollower::STATUS_PENDING)
            ->with('follower')
            ->orderBy('created_at', 'desc');
        $total = $query->count();
        $requests = $query->skip(($page - 1) * $limit)->take($limit)->get()
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

        return ApiResponse::success($requests, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * POST /user/accept-follow-request - Body: follower_id (accept a pending follow request).
     * Only the account that was requested (following_id) can accept.
     */
    public function acceptFollowRequest(Request $request, ApiCacheService $cache)
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
        $this->invalidateRelationshipCaches($cache, [$user->id, $followerId], true);
        return ApiResponse::success(['message' => 'Follow request accepted']);
    }

    /**
     * POST /user/reject-follow-request - Body: follower_id (reject a pending follow request).
     */
    public function rejectFollowRequest(Request $request, ApiCacheService $cache)
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
        $this->invalidateRelationshipCaches($cache, [$user->id, $followerId], true);
        return ApiResponse::success(['message' => 'Follow request rejected']);
    }

    /**
     * POST /user/unfollow - Body: following_id or user_id
     */
    public function unfollow(Request $request, ApiCacheService $cache)
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
        $this->invalidateRelationshipCaches($cache, [$user->id, $followingId], true);
        return ApiResponse::success(['message' => 'Unfollowed']);
    }

    /**
     * POST /api/v1/user/add-friend - Body: target_id (user ID to add as friend).
     * Auth: Bearer token required.
     * Creates a pending friend request (or auto-accept per receiver privacy config).
     */
    public function addFriend(Request $request, ApiCacheService $cache)
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

        // Already friends?
        $existingFriendship = Friendship::where(function ($q) use ($user, $targetId) {
            $q->where('user_id', $user->id)->where('friend_id', $targetId);
        })->orWhere(function ($q) use ($user, $targetId) {
            $q->where('user_id', $targetId)->where('friend_id', $user->id);
        })->first();

        if ($existingFriendship && $existingFriendship->status === Friendship::STATUS_ACCEPTED) {
            return response()->json([
                'success' => false,
                'message' => 'Already friends.',
            ], 400);
        }

        // Existing pending / rejected request?
        $existingRequest = FriendRequest::where(function ($q) use ($user, $targetId) {
            $q->where('sender_id', $user->id)->where('receiver_id', $targetId);
        })->orWhere(function ($q) use ($user, $targetId) {
            $q->where('sender_id', $targetId)->where('receiver_id', $user->id);
        })->first();

        if ($existingRequest) {
            if ($existingRequest->status === FriendRequest::STATUS_PENDING) {
                if ((int) $existingRequest->sender_id === (int) $user->id) {
                    return response()->json([
                        'success' => true,
                        'status' => 'request_sent',
                    ], 200);
                }

                return response()->json([
                    'success' => true,
                    'status' => 'request_received',
                ], 200);
            }
            if ($existingRequest->status === FriendRequest::STATUS_ACCEPTED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already friends.',
                ], 400);
            }
            // If previously rejected, allow sending a new request by updating status back to pending
            $existingRequest->update(['status' => FriendRequest::STATUS_PENDING]);
            $friendRequest = $existingRequest;
        } else {
            $friendRequest = FriendRequest::create([
                'sender_id' => $user->id,
                'receiver_id' => $targetId,
                'status' => FriendRequest::STATUS_PENDING,
            ]);
        }

        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($targetId, 'Friend request', ($user->display_name ?? $user->name ?? 'Someone') . ' sent you a friend request', ['type' => 'friend_request', 'user_id' => (string) $user->id]);
        }

        $this->invalidateRelationshipCaches($cache, [$user->id, $targetId]);

        return response()->json([
            'success' => true,
            'status' => 'request_sent',
        ], 200);
    }

    /**
     * POST /user/friend-request - Body: user_id (alias for add-friend). Send friend request.
     */
    public function sendFriendRequest(Request $request, ApiCacheService $cache)
    {
        $request->merge([
            'target_id' => $request->input('user_id'),
        ]);
        return $this->addFriend($request, $cache);
    }

    /**
     * POST /user/friend-request/accept - Body: request_id (Friendship id) or user_id. Accept friend request.
     */
    public function acceptFriendRequestByRequest(Request $request, ApiCacheService $cache)
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
            $requestRow = FriendRequest::where('id', $validated['request_id'])
                ->where('receiver_id', $user->id)
                ->where('status', FriendRequest::STATUS_PENDING)
                ->first();
        } elseif (!empty($validated['user_id'])) {
            $requestRow = FriendRequest::where('sender_id', $validated['user_id'])
                ->where('receiver_id', $user->id)
                ->where('status', FriendRequest::STATUS_PENDING)
                ->first();
        }
        if (!$requestRow) {
            return ApiResponse::notFound('Friend request not found');
        }
        $friendId = $requestRow->sender_id;
        $requestRow->update(['status' => FriendRequest::STATUS_ACCEPTED]);

        // Create mutual friendships only on acceptance
        Friendship::firstOrCreate([
            'user_id' => $user->id,
            'friend_id' => $friendId,
        ], ['status' => Friendship::STATUS_ACCEPTED]);
        Friendship::firstOrCreate([
            'user_id' => $friendId,
            'friend_id' => $user->id,
        ], ['status' => Friendship::STATUS_ACCEPTED]);

        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($friendId, 'Friend request accepted', ($user->display_name ?? $user->name ?? 'Someone') . ' accepted your friend request', ['type' => 'friend_accepted', 'user_id' => (string) $user->id]);
        }
        $this->invalidateRelationshipCaches($cache, [$user->id, $friendId]);
        return ApiResponse::success(['message' => 'Friend request accepted', 'status' => 'friends']);
    }

    /**
     * POST /user/friend-request/decline - Body: user_id. Decline incoming or cancel outgoing friend request.
     */
    public function declineFriendRequest(Request $request, ApiCacheService $cache)
    {
        try {
            $validated = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $otherId = (int) $validated['user_id'];

        $requestRow = FriendRequest::where(function ($q) use ($user, $otherId) {
            $q->where('sender_id', $otherId)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($user, $otherId) {
            $q->where('sender_id', $user->id)->where('receiver_id', $otherId);
        })->where('status', FriendRequest::STATUS_PENDING)->first();

        if (!$requestRow) {
            return ApiResponse::notFound('Friend request not found or already handled');
        }

        $requestRow->update(['status' => FriendRequest::STATUS_REJECTED]);

        $this->invalidateRelationshipCaches($cache, [$user->id, $otherId]);
        return ApiResponse::success(['message' => 'Friend request declined']);
    }

    /**
     * GET /user/friend-requests - Pending friend requests to the current user.
     * Returns list of { id, user_id, name, avatar, level } (requester = user_id).
     */
    /**
     * GET /user/friend-requests - Incoming friend requests. Paginated.
     * Query: page (default 1), limit (default 20, max 50).
     */
    public function friendRequests(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);

        $query = FriendRequest::where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->with('sender')
            ->orderBy('created_at', 'desc');
        $total = $query->count();
        $requests = $query->skip(($page - 1) * $limit)->take($limit)->get()
            ->map(function (FriendRequest $f) {
                $requester = $f->sender;
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

        return ApiResponse::success($requests, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * GET /user/friend-requests/count - Pending friend requests count for current user.
     */
    public function friendRequestsCount(Request $request)
    {
        $user = $request->user();
        $count = FriendRequest::where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->count();

        return ApiResponse::success(['count' => (int) $count]);
    }

    /**
     * POST /user/accept-friend - Body: friend_id (accept incoming request)
     */
    public function acceptFriend(Request $request, ApiCacheService $cache)
    {
        try {
            $validated = $request->validate(['friend_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $friendId = (int) $validated['friend_id'];

        $requestRow = FriendRequest::where('sender_id', $friendId)
            ->where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->first();

        if (!$requestRow) {
            return ApiResponse::notFound('Friend request not found');
        }

        $requestRow->update(['status' => FriendRequest::STATUS_ACCEPTED]);

        // Create symmetric friendships (no status column used anymore)
        Friendship::firstOrCreate(
            ['user_id' => $user->id, 'friend_id' => $friendId]
        );
        Friendship::firstOrCreate(
            ['user_id' => $friendId, 'friend_id' => $user->id]
        );

        if ($this->firebase->isConfigured()) {
            $this->firebase->sendToUser($friendId, 'Friend request accepted', ($user->display_name ?? $user->name ?? 'Someone') . ' accepted your friend request', ['type' => 'friend_accepted', 'user_id' => (string) $user->id]);
        }
        $this->invalidateRelationshipCaches($cache, [$user->id, $friendId]);
        return ApiResponse::success(['message' => 'Friend request accepted', 'status' => 'friends']);
    }

    /**
     * POST /user/reject-friend - Body: friend_id (decline incoming request)
     */
    public function rejectFriend(Request $request, ApiCacheService $cache)
    {
        try {
            $validated = $request->validate(['friend_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $friendId = (int) $validated['friend_id'];

        // Reject the pending friend request using FriendRequest status
        $requestRow = FriendRequest::where('sender_id', $friendId)
            ->where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->first();

        if (!$requestRow) {
            return ApiResponse::notFound('Friend request not found or already handled');
        }

        $requestRow->update(['status' => FriendRequest::STATUS_REJECTED]);

        $this->invalidateRelationshipCaches($cache, [$user->id, $friendId]);
        return ApiResponse::success(['message' => 'Friend request declined', 'status' => 'rejected']);
    }

    /**
     * POST /user/unfriend - Body: friend_id
     */
    public function unfriend(Request $request, ApiCacheService $cache)
    {
        try {
            $validated = $request->validate(['friend_id' => 'required|integer|exists:users,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $friendId = (int) $validated['friend_id'];

        if ($friendId === $user->id) {
            return ApiResponse::error('INVALID_REQUEST', 'Cannot unfriend yourself', 400);
        }

        // Remove both directions from friendships
        Friendship::where(function ($q) use ($user, $friendId) {
            $q->where('user_id', $user->id)->where('friend_id', $friendId);
        })->orWhere(function ($q) use ($user, $friendId) {
            $q->where('user_id', $friendId)->where('friend_id', $user->id);
        })->delete();

        // Optionally, ensure any pending friend requests between them are marked cancelled
        FriendRequest::where(function ($q) use ($user, $friendId) {
            $q->where('sender_id', $user->id)->where('receiver_id', $friendId);
        })->orWhere(function ($q) use ($user, $friendId) {
            $q->where('sender_id', $friendId)->where('receiver_id', $user->id);
        })->where('status', FriendRequest::STATUS_PENDING)->update([
            'status' => FriendRequest::STATUS_CANCELLED,
        ]);

        $this->invalidateRelationshipCaches($cache, [$user->id, $friendId]);

        return ApiResponse::success(['success' => true]);
    }

    /**
     * POST /user/block - Body: blocked_user_id
     */
    public function block(Request $request, ApiCacheService $cache)
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
        $this->invalidateRelationshipCaches($cache, [$user->id, $blockedId]);
        return ApiResponse::success(['message' => 'User blocked']);
    }

    /**
     * GET /users/{id}/followers - List of users following this user. Paginated.
     * Query: page (default 1), limit (default 20, max 100).
     */
    public function followers(Request $request, $id)
    {
        $viewer = $request->user();
        $id = $this->resolveRequestedUserId($request, $id);
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        $query = UserFollower::where('following_id', $id)
            ->where('status', UserFollower::STATUS_ACCEPTED)
            ->with('follower')
            ->orderBy('created_at', 'desc');
        $total = $query->count();
        $records = $query->skip(($page - 1) * $limit)->take($limit)->get();
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
        return ApiResponse::success($list, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * GET /users/{id}/following - List of users this user is following. Paginated.
     * Query: page (default 1), limit (default 20, max 100).
     */
    public function following(Request $request, $id)
    {
        $viewer = $request->user();
        $id = $this->resolveRequestedUserId($request, $id);
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        $query = UserFollower::where('follower_id', $id)
            ->where('status', UserFollower::STATUS_ACCEPTED)
            ->with('following')
            ->orderBy('created_at', 'desc');
        $total = $query->count();
        $records = $query->skip(($page - 1) * $limit)->take($limit)->get();
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
        return ApiResponse::success($list, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * GET /users/{id}/friends - List of friends for a user (symmetric friendships). Paginated.
     * Query: page (default 1), limit (default 20, max 100).
     */
    public function friendsForUser(Request $request, $id)
    {
        $viewer = $request->user();
        $id = $this->resolveRequestedUserId($request, $id);
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        $query = Friendship::where(function ($q) use ($id) {
            $q->where('user_id', $id)->orWhere('friend_id', $id);
        })->with(['user', 'friend'])->orderBy('created_at', 'desc');

        $total = $query->count();
        $rows = $query->skip(($page - 1) * $limit)->take($limit)->get();

        $viewerId = (int) $viewer->id;
        $friends = $rows->map(function (Friendship $f) use ($id, $viewerId) {
            $friend = $f->user_id === $id ? $f->friend : $f->user;
            if (!$friend) {
                return null;
            }
            $online = User::getOnlineStatusForViewer($friend, $viewerId);
            return [
                'id' => $friend->id,
                'name' => $friend->display_name ?? $friend->name ?? 'User',
                'avatar_url' => $friend->avatar_url,
                'is_online' => $online['is_online'],
                'last_seen_at' => $online['last_seen_at'],
            ];
        })->filter()->values()->all();

        return ApiResponse::success($friends, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * GET /api/v1/users/{id}/media - Paginated posts and reels for a user's profile.
     * Query: page (default 1), limit (default 20). Returns is_liked and is_saved for the authenticated user.
     */
    public function media(Request $request, $id)
    {
        $id = $this->resolveRequestedUserId($request, $id);
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        if (!User::where('id', $id)->exists()) {
            return ApiResponse::notFound('User not found');
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);

        $paginator = MediaPost::where('user_id', $id)
            ->select(MediaPost::FEED_SELECT)
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        $collection = $paginator->getCollection();
        $postIds = $collection->pluck('id')->all();
        [$likedIds, $savedIds] = $this->viewerLikeAndSaveIdsForMedia($request, $postIds);

        $data = $collection->map(function (MediaPost $item) use ($likedIds, $savedIds) {
            return [
                'id' => 'post_' . $item->id,
                'user_id' => (string) $item->user_id,
                'type' => $item->type,
                'media_type' => $item->media_type,
                'file_url' => $item->file_url,
                'thumbnail_url' => $item->thumbnail_url,
                'caption' => $item->caption ?? '',
                'likes' => (int) $item->likes,
                'comments' => (int) $item->comments,
                'shares' => (int) ($item->shares ?? 0),
                'is_liked' => isset($likedIds[$item->id]),
                'is_saved' => isset($savedIds[$item->id]),
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * Get sets of post IDs the current user has liked and saved (for profile media).
     */
    private function viewerLikeAndSaveIdsForMedia(Request $request, array $postIds): array
    {
        if (empty($postIds)) {
            return [[], []];
        }
        $userId = $request->user()?->id;
        if (!$userId) {
            return [[], []];
        }
        $likedIds = PostLike::where('user_id', $userId)->whereIn('media_post_id', $postIds)->pluck('media_post_id')->flip()->all();
        $savedIds = PostSave::where('user_id', $userId)->whereIn('media_post_id', $postIds)->pluck('media_post_id')->flip()->all();
        return [$likedIds, $savedIds];
    }

    private function invalidateRelationshipCaches(ApiCacheService $cache, array $userIds, bool $invalidateReelFeed = false): void
    {
        if (!empty(array_unique(array_filter($userIds)))) {
            $cache->bumpVersion('profile');
        }

        if ($invalidateReelFeed) {
            $cache->bumpVersion('reels');
        }
    }

    /**
     * GET /users/{id} or GET /user/{id} - Public profile by database primary key (integer).
     * Use the user's DB id (e.g. 5), not Agora UID or any other identifier.
     */
    public function show(Request $request, $id, ApiCacheService $cache)
    {
        $me = $request->user();
        $id = $this->resolveRequestedUserId($request, $id);
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $target = User::find($id);
        if (!$target) {
            return ApiResponse::notFound('User not found');
        }
        $ttl = $cache->ttl('profile');
        $cacheKey = $cache->versionedKey('profile', [
            'target' => $target->id,
            'viewer' => $me->id,
        ]);

        $data = $cache->remember($cacheKey, $ttl, function () use ($me, $target) {
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
            })->count();
            $isFriend = Friendship::where(function ($q) use ($me, $target) {
                $q->where('user_id', $me->id)->where('friend_id', $target->id);
            })->orWhere(function ($q) use ($me, $target) {
                $q->where('user_id', $target->id)->where('friend_id', $me->id);
            })->exists();

            // Relationship status: none, request_sent, request_received, friends
            $relationshipStatus = 'none';
            if ($isFriend) {
                $relationshipStatus = 'friends';
            } else {
                $sentPending = FriendRequest::where('sender_id', $me->id)
                    ->where('receiver_id', $target->id)
                    ->where('status', FriendRequest::STATUS_PENDING)
                    ->exists();
                $receivedPending = FriendRequest::where('sender_id', $target->id)
                    ->where('receiver_id', $me->id)
                    ->where('status', FriendRequest::STATUS_PENDING)
                    ->exists();
                if ($sentPending) {
                    $relationshipStatus = 'request_sent';
                } elseif ($receivedPending) {
                    $relationshipStatus = 'request_received';
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
            $isFriendRequestSent = $relationshipStatus === 'request_sent';
            $hidePrivateCounts = $target->private_account === true && !$isConfirmedFollower;
            $followersCountForResponse = $hidePrivateCounts ? null : $followersCount;
            $followingCountForResponse = $hidePrivateCounts ? null : $followingCount;

            return [
                'id' => (int) $target->id,
                'name' => $target->display_name ?? $target->name ?? 'User',
                'avatar' => $target->avatar_url ?? '',
                'level' => (int) ($target->level ?? 0),
                'country' => $countryName,
                'countryFlag' => $countryFlag,
                'followersCount' => $followersCountForResponse,
                'followingCount' => $followingCountForResponse,
                'friendsCount' => $friendsCount,
                'isFollowing' => $isFollowing,
                'isFriend' => $isFriend,
                'relationship_status' => $relationshipStatus,
                'isFriendRequestSent' => $isFriendRequestSent,
                'isBlocked' => $isBlocked,
                'hasBlockedMe' => $hasBlockedMe,
                'lastSeenAt' => $onlineForViewer['last_seen_at'] ?? null,
                'isOnline' => $onlineForViewer['is_online'] ?? false,
                'selectedFrameId' => $target->selected_frame_id ? (int) $target->selected_frame_id : null,
                'gender' => $target->gender,
                'country_flag' => $countryFlag,
                'follow_request_pending' => $followRequestPending ?? false,
                'friend_request_status' => $relationshipStatus, // backward compat
                'last_seen_at' => $onlineForViewer['last_seen_at'] ?? null,
                'is_online' => $onlineForViewer['is_online'] ?? false,
                'private_account' => (bool) $target->private_account,
                'show_online_status' => (bool) $target->show_online_status,
            ];
        });

        // For interactive social actions (follow / add friend), we want the
        // freshest possible profile view. Keep Redis caching for server-side
        // performance, but disable HTTP-level caching so Nginx / clients do
        // not serve stale data after relationship changes.
        $response = ApiResponse::success($data);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->remove('ETag');

        return $response;
    }

    /**
     * GET /users/{id}/gifts - Gift gallery: gifts received by this user (Charm tab). Paginated.
     * Query: page (default 1), limit (default 20, max 50).
     */
    public function gifts(Request $request, $id)
    {
        $id = $this->resolveRequestedUserId($request, $id);
        if ($id <= 0) {
            return ApiResponse::error('INVALID_ID', 'User id must be a positive integer', 400);
        }
        $user = User::find($id);
        if (!$user) {
            return ApiResponse::notFound('User not found');
        }
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);

        $received = CoinTransaction::where('receiver_id', $id)
            ->where('transaction_type', CoinTransaction::TYPE_GIFT)
            ->whereNotNull('reference_id')
            ->selectRaw('reference_id as gift_id, count(*) as count_received')
            ->groupBy('reference_id')
            ->get();

        $giftIds = $received->pluck('gift_id')->unique()->filter()->values()->all();
        $giftsById = VirtualGift::whereIn('id', $giftIds)->get()->keyBy('id');
        $countByGift = $received->keyBy('gift_id');

        $all = $received->map(function ($row) use ($giftsById, $countByGift) {
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
        $total = count($all);
        $gifts = array_slice($all, ($page - 1) * $limit, $limit);

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
        ], ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * GET /users/{id}/privileges - Wealth privileges list (Wealth Dashboard tab).
     */
    public function privileges(Request $request, $id)
    {
        $id = $this->resolveRequestedUserId($request, $id);
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

    private function resolveRequestedUserId(Request $request, mixed $id): int
    {
        if (is_int($id) && $id > 0) {
            return $id;
        }

        $raw = trim((string) $id);
        if ($raw === '') {
            return (int) ($request->user()?->id ?? 0);
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        if (in_array(strtolower($raw), ['me', 'self', 'null', 'undefined'], true)) {
            return (int) ($request->user()?->id ?? 0);
        }

        $fallbackId = (int) ($request->user()?->id ?? 0);
        if ($fallbackId > 0) {
            \Log::warning('Resolved invalid user id to authenticated user', [
                'raw_id' => $id,
                'fallback_user_id' => $fallbackId,
            ]);
        }

        return $fallbackId;
    }
}
