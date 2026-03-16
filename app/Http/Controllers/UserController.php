<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\BlockedUser;
use App\Models\Country;
use App\Models\Friendship;
use App\Models\FriendRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserFollower;
use App\Services\ApiCacheService;
use App\Services\BunnyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private BunnyStorageService $bunny
    ) {}

    /**
     * GET /users/search - Search users by ID (exact) or by name/email (fuzzy).
     * If query is purely numeric, exact match on users.id first; else name-based search.
     */
    public function search(Request $request)
    {
        $query = $request->query('q') ?? $request->query('query') ?? '';
        $query = trim((string) $query);
        if ($query === '') {
            return ApiResponse::success([]);
        }

        $viewerId = (int) $request->user()->id;
        if (ctype_digit($query)) {
            $user = User::find((int) $query);
            if ($user && $user->id !== $viewerId) {
                $online = User::getOnlineStatusForViewer($user, $viewerId);
                return ApiResponse::success([
                    [
                        'id' => $user->id,
                        'name' => $user->display_name ?? $user->name ?? 'User',
                        'avatar_url' => $user->avatar_url,
                        'is_online' => $online['is_online'],
                        'last_seen_at' => $online['last_seen_at'],
                        'private_account' => (bool) $user->private_account,
                        'show_online_status' => (bool) $user->show_online_status,
                    ],
                ]);
            }
            return ApiResponse::success([]);
        }

        $term = '%' . $query . '%';
        $users = User::where('id', '!=', $viewerId)
            ->where(function ($q) use ($term) {
                $q->where('display_name', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            })
            ->limit(50)
            ->get()
            ->map(function (User $u) use ($viewerId) {
                $online = User::getOnlineStatusForViewer($u, $viewerId);
                return [
                    'id' => $u->id,
                    'name' => $u->display_name ?? $u->name ?? 'User',
                    'avatar_url' => $u->avatar_url,
                    'is_online' => $online['is_online'],
                    'last_seen_at' => $online['last_seen_at'],
                    'private_account' => (bool) $u->private_account,
                    'show_online_status' => (bool) $u->show_online_status,
                ];
            })
            ->values()
            ->all();

        return ApiResponse::success($users);
    }

    /**
     * GET /user/profile - Profile for app (ProfileDto shape).
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $friendsCount = Friendship::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
        })->count();
        $followersCount = UserFollower::where('following_id', $user->id)->where('status', \App\Models\UserFollower::STATUS_ACCEPTED)->count();
        $followingCount = UserFollower::where('follower_id', $user->id)->where('status', \App\Models\UserFollower::STATUS_ACCEPTED)->count();
        $friendRequestsCount = FriendRequest::where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->count();
        $coins = (int) ($user->wallet_balance ?? $user->coin_balance ?? 0);
        $data = [
            'id' => (int) $user->id,
            'name' => $user->display_name ?? $user->name ?? 'User',
            'avatar' => $user->avatar_url,
            'level' => (int) ($user->level ?? 0),
            'friends_count' => (int) $friendsCount,
            'followers_count' => (int) $followersCount,
            'following_count' => (int) $followingCount,
            'friend_requests_count' => (int) $friendRequestsCount,
            'fans_count' => 0,
            'coins' => (int) $coins,
            'referral_code' => $user->invite_code,
            'referral_balance' => (int) ($user->referral_balance ?? 0),
            'gems' => (int) ($user->gems ?? 0),
            'language' => $user->language,
            'country' => $this->resolveCountryForUser($user)['country'],
            'country_flag' => $this->resolveCountryForUser($user)['country_flag'],
            'bio' => $user->bio,
            'gender' => $user->gender,
            'dob' => $user->dob ? $user->dob->format('Y-m-d') : null,
            'private_account' => (bool) $user->private_account,
            'show_online_status' => (bool) $user->show_online_status,
            'is_online' => $user->isOnline(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
        return ApiResponse::success($data);
    }

    /**
     * POST /user/update - Multipart: name, avatar, country, bio, gender, dob (all optional).
     * Content-Type: multipart/form-data. Returns updated user object with profile fields.
     */
    public function update(Request $request, ApiCacheService $cache)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|max:5120', // 5MB
            'country' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:500',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date_format:Y-m-d',
        ]);
        $user = $request->user();
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            $cdnBase = rtrim(config('bunny.cdn_url', ''), '/');
            if ($user->avatar_url && $cdnBase !== '' && str_starts_with($user->avatar_url, $cdnBase . '/')) {
                $oldPath = substr($user->avatar_url, strlen($cdnBase . '/'));
                if ($oldPath !== '') {
                    $this->bunny->deleteFile($oldPath);
                }
            }
            $ext = strtolower($avatarFile->getClientOriginalExtension() ?: 'jpg');
            $path = 'avatars/' . $user->id . '/' . (string) Str::ulid() . '.' . $ext;
            try {
                $user->avatar_url = $this->bunny->uploadImage($avatarFile, $path);
            } catch (\Throwable $e) {
                return ApiResponse::error('UPLOAD_FAILED', 'Avatar upload failed.', 500);
            }
        }
        if ($request->filled('name')) {
            $user->display_name = $request->input('name');
            $user->name = $request->input('name');
        }
        if ($request->has('country')) {
            $user->country = $this->resolveCountryIdFromInput($request->input('country')) ?? $request->input('country');
        }
        if ($request->has('bio')) {
            $user->bio = $request->input('bio') ?: null;
        }
        if ($request->has('gender')) {
            $user->gender = $request->input('gender') ?: null;
        }
        if ($request->has('dob')) {
            $user->dob = $request->input('dob') ?: null;
        }
        $user->save();
        $user->refresh();
        $cache->bumpVersion('profile');
        $resolved = $this->resolveCountryForUser($user);
        $walletBalance = (int) ($user->wallet_balance ?? $user->coin_balance ?? 0);
        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->display_name ?? $user->name ?? 'User',
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'gender' => $user->gender,
            'dob' => $user->dob ? $user->dob->format('Y-m-d') : null,
            'country' => $resolved['country'],
            'wallet_balance' => $walletBalance,
            'role' => $user->role ?? 'user',
        ]);
    }

    /**
     * POST /user/update-language - Body: language (code).
     */
    public function updateLanguage(Request $request)
    {
        $request->validate(['language' => 'required|string|max:10']);
        $user = $request->user();
        $user->language = $request->input('language');
        $user->save();
        return ApiResponse::success(['message' => 'Language updated', 'language' => $user->language]);
    }

    /**
     * GET /user/blocked-users - List blocked users. Paginated.
     * Query: page (default 1), limit (default 20, max 50).
     */
    public function blockedUsers(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 50);
        $query = BlockedUser::where('blocker_id', $user->id)->with('blocked')->orderBy('created_at', 'desc');
        $total = $query->count();
        $list = $query->skip(($page - 1) * $limit)->take($limit)->get()->map(fn ($b) => [
            'id' => $b->blocked->id,
            'name' => $b->blocked->display_name ?? $b->blocked->name,
            'avatar_url' => $b->blocked->avatar_url,
        ])->values()->all();
        return ApiResponse::success($list, ApiResponse::paginationMeta($total, $page, $limit));
    }

    /**
     * POST /api/v1/user/unblock - Body: blocked_user_id (ID of user to unblock).
     * Also accepts user_id for backward compatibility.
     */
    public function unblock(Request $request, ApiCacheService $cache)
    {
        $request->validate([
            'blocked_user_id' => 'required_without:user_id|integer|exists:users,id',
            'user_id' => 'required_without:blocked_user_id|integer|exists:users,id',
        ]);
        $user = $request->user();
        $blockedId = (int) ($request->input('blocked_user_id') ?? $request->input('user_id'));
        BlockedUser::where('blocker_id', $user->id)->where('blocked_id', $blockedId)->delete();
        $cache->bumpVersion('profile');
        return response()->json([
            'success' => true,
            'message' => 'User unblocked successfully.',
        ], 200);
    }

    /**
     * POST /user/device or POST /device/register - Register FCM token for push notifications.
     * Body: fcm_token (required), platform (optional), device_type (optional, e.g. android, ios, web).
     */
    public function registerDevice(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
            'platform' => 'nullable|string|in:android,ios,web|max:20',
            'device_type' => 'nullable|string|max:20',
        ]);
        $user = $request->user();
        $platform = $request->input('platform') ?? $request->input('device_type') ?? 'android';
        $deviceType = $request->input('device_type') ?? $platform;
        UserDevice::updateOrCreate(
            ['user_id' => $user->id, 'platform' => $platform],
            ['fcm_token' => $request->input('fcm_token'), 'device_type' => $deviceType]
        );
        return ApiResponse::success(['message' => 'Device registered']);
    }

    /**
     * POST /update-fcm-token - Save FCM token to user for push (incoming call, new message).
     * High-priority delivery when app closed/background.
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);
        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();
        return response()->json(['success' => true]);
    }

    /**
     * POST /user/delete - Soft or hard delete account.
     */
    public function delete(Request $request)
    {
        $user = $request->user();
        // Revoke tokens, optionally soft-delete or anonymize
        $user->refreshTokens()->delete();
        $user->update([
            'name' => 'Deleted User',
            'display_name' => 'Deleted User',
            'email' => null,
            'phone' => null,
            'avatar_url' => null,
            'password' => \Hash::make(\Str::random(32)),
        ]);
        return ApiResponse::success(['message' => 'Account deleted']);
    }

    /**
     * POST /user/privacy - Body: private_account, show_online_status (booleans).
     */
    public function privacy(Request $request)
    {
        $request->validate([
            'private_account' => 'nullable|boolean',
            'show_online_status' => 'nullable|boolean',
        ]);
        $user = $request->user();
        if ($request->has('private_account')) {
            $user->private_account = (bool) $request->input('private_account');
        }
        if ($request->has('show_online_status')) {
            $user->show_online_status = (bool) $request->input('show_online_status');
        }
        $user->save();
        return ApiResponse::success(['message' => 'Privacy updated']);
    }

    /**
     * POST /user/notifications - Body: message_notifications, room_notifications, gift_notifications (booleans).
     */
    public function notifications(Request $request)
    {
        $request->validate([
            'message_notifications' => 'nullable|boolean',
            'room_notifications' => 'nullable|boolean',
            'gift_notifications' => 'nullable|boolean',
        ]);
        $user = $request->user();
        if ($request->has('message_notifications')) {
            $user->message_notifications = (bool) $request->input('message_notifications');
        }
        if ($request->has('room_notifications')) {
            $user->room_notifications = (bool) $request->input('room_notifications');
        }
        if ($request->has('gift_notifications')) {
            $user->gift_notifications = (bool) $request->input('gift_notifications');
        }
        $user->save();
        return ApiResponse::success(['message' => 'Notifications updated']);
    }

    /**
     * Get current user profile (legacy GET /users/me). Includes country, country_flag, bio, gender, dob.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $data = $user->makeHidden(['password', 'remember_token', 'fcm_token'])->toArray();
        if (isset($data['dob']) && $data['dob']) {
            $data['dob'] = $user->dob->format('Y-m-d');
        }
        $resolved = $this->resolveCountryForUser($user);
        $data['country'] = $resolved['country'];
        $data['country_flag'] = $resolved['country_flag'];
        $data['referral_code'] = $user->invite_code;
        $data['referral_balance'] = (int) ($user->referral_balance ?? 0);
        $data['gems'] = (int) ($user->gems ?? 0);
        $data['friend_requests_count'] = (int) FriendRequest::where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->count();
        $data['private_account'] = (bool) $user->private_account;
        $data['show_online_status'] = (bool) $user->show_online_status;
        $data['is_online'] = $user->isOnline();
        $data['last_seen_at'] = $user->last_seen_at?->toIso8601String();
        $data['email_verified_at'] = $user->email_verified_at?->toIso8601String();
        return ApiResponse::success($data);
    }

    /**
     * Update current user profile (PATCH /users/me). country accepts country id (e.g. IN) or name (e.g. India).
     */
    public function updateMe(Request $request, ApiCacheService $cache)
    {
        try {
            $validated = $request->validate([
                'display_name' => 'nullable|string|max:255',
                'avatar_url' => 'nullable|url|max:500',
                'country' => 'nullable|string|max:100',
            ]);

            $user = $request->user();

            if (isset($validated['display_name'])) {
                $user->display_name = $validated['display_name'];
            }
            if (isset($validated['avatar_url'])) {
                $user->avatar_url = $validated['avatar_url'];
            }
            if (array_key_exists('country', $validated)) {
                $user->country = $this->resolveCountryIdFromInput($validated['country']) ?? $validated['country'];
            }

            $user->save();
            $cache->bumpVersion('profile');

            $data = $user->makeHidden(['password', 'remember_token', 'fcm_token'])->toArray();
            if (isset($data['dob']) && $data['dob']) {
                $data['dob'] = $user->dob->format('Y-m-d');
            }
            $resolved = $this->resolveCountryForUser($user);
            $data['country'] = $resolved['country'];
            $data['country_flag'] = $resolved['country_flag'];
            $data['referral_code'] = $user->invite_code;
            $data['referral_balance'] = (int) ($user->referral_balance ?? 0);
            $data['email_verified_at'] = $user->email_verified_at?->toIso8601String();
            return ApiResponse::success($data);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('UPDATE_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Resolve user's country string to display name and flag URL from countries table.
     */
    private function resolveCountryForUser(User $user): array
    {
        if (!$user->country) {
            return ['country' => null, 'country_flag' => null];
        }
        if (strtolower(trim($user->country)) === 'other') {
            return ['country' => 'Other', 'country_flag' => null];
        }
        $country = Country::find($user->country);
        if ($country) {
            return ['country' => $country->name, 'country_flag' => $country->flag_url];
        }
        return ['country' => $user->country, 'country_flag' => null];
    }

    /**
     * Resolve input (country id or name) to country id from master list.
     */
    private function resolveCountryIdFromInput(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        $input = trim($input);
        if (strtolower($input) === 'other') {
            return null;
        }
        $byId = Country::find($input);
        if ($byId) {
            return $byId->id;
        }
        $byName = Country::where('name', $input)->orWhereRaw('LOWER(name) = ?', [strtolower($input)])->first();
        return $byName ? $byName->id : null;
    }

    /**
     * Get wallet/balance.
     */
    public function wallet(Request $request)
    {
        $user = $request->user();
        $balance = (int) ($user->wallet_balance ?? 0);
        $totalEarnedCoins = (int) ($user->total_earned_coins ?? 0);

        return ApiResponse::success([
            'wallet_balance' => $balance,
            'total_earned_coins' => $totalEarnedCoins,
        ]);
    }

    /**
     * Get user transactions.
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        
        $type = $request->query('type', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        if ($type === 'sent') {
            $query = $user->sentTransactions()->with(['receiver', 'giftType', 'room']);
        } elseif ($type === 'received') {
            $query = $user->receivedTransactions()->with(['sender', 'giftType', 'room']);
        } else {
            // For 'all', we need to combine both
            $sent = $user->sentTransactions()->with(['receiver', 'giftType', 'room'])->get();
            $received = $user->receivedTransactions()->with(['sender', 'giftType', 'room'])->get();
            $all = $sent->concat($received)->sortByDesc('created_at');
            
            $total = $all->count();
            $paginated = $all->slice(($page - 1) * $limit, $limit)->values();

            return ApiResponse::success($paginated, ApiResponse::paginationMeta($total, $page, $limit));
        }

        $total = $query->count();
        $transactions = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return ApiResponse::success($transactions, ApiResponse::paginationMeta($total, $page, $limit));
    }
}

