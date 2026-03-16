<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MusicController;
use App\Http\Controllers\ProfileLevelController;
use App\Http\Controllers\PostEngagementController;
use App\Http\Controllers\PostFeedController;
use App\Http\Controllers\PostMediaController;
use App\Http\Controllers\ReelController;
use App\Http\Controllers\ReelsFeedController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\SpinController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInteractionController;
use App\Http\Controllers\UserMediaController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
| Base URL: /api/v1
| Auth: Bearer token for protected routes (except auth/register, auth/login, etc.)
| All list endpoints support pagination: ?page=1&limit=20 (where applicable).
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Auth (public)
    // -------------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'google']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // -------------------------------------------------------------------------
    // Protected routes
    // -------------------------------------------------------------------------
    Route::middleware(['auth.api', 'check.suspension'])->group(function () {

        // Auth (protected: OTP, change password)
        Route::prefix('auth')->group(function () {
            Route::post('/send-email-otp', [AuthController::class, 'sendEmailOtp']);
            Route::post('/verify-email-otp', [AuthController::class, 'verifyEmailOtp']);
            Route::post('/change-password/request', [AuthController::class, 'changePasswordRequest']);
            Route::post('/change-password/verify', [AuthController::class, 'changePasswordVerify']);
        });

        // ---------------------------------------------------------------------
        // Users & profile (paginated where list)
        // ---------------------------------------------------------------------
        Route::prefix('users')->group(function () {
            Route::get('/me', [UserController::class, 'me']);
            Route::patch('/me', [UserController::class, 'updateMe']);
            Route::get('/me/wallet', [UserController::class, 'wallet']);
            Route::get('/me/balance', [UserController::class, 'wallet']);
            Route::get('/me/transactions', [UserController::class, 'transactions']);
            Route::get('/search', [UserController::class, 'search']);
            Route::post('/follow', [UserInteractionController::class, 'follow']);
            Route::post('/unfollow', [UserInteractionController::class, 'unfollow']);
            Route::post('/friend-request', [UserInteractionController::class, 'sendFriendRequest']);
            Route::get('/{id}/followers', [UserInteractionController::class, 'followers']);
            Route::get('/{id}/following', [UserInteractionController::class, 'following']);
            Route::get('/{id}/gifts', [UserInteractionController::class, 'gifts']);
            Route::get('/{id}/privileges', [UserInteractionController::class, 'privileges']);
            Route::get('/{id}/media', [UserInteractionController::class, 'media']);
            Route::get('/{id}', [UserInteractionController::class, 'show']);
        });

        // Profile & settings (legacy paths kept for app compatibility)
        Route::get('/user/profile', [UserController::class, 'profile']);
        Route::post('/user/update', [UserController::class, 'update']);
        Route::get('/user/blocked-users', [UserController::class, 'blockedUsers']);
        Route::post('/user/unblock', [UserController::class, 'unblock']);
        Route::post('/user/device', [UserController::class, 'registerDevice']);
        Route::post('/user/delete', [UserController::class, 'delete']);
        Route::post('/user/privacy', [UserController::class, 'privacy']);
        Route::post('/user/notifications', [UserController::class, 'notifications']);
        Route::post('/upload', [UploadController::class, 'upload']);
        Route::post('/user/update-language', [UserController::class, 'updateLanguage']);

        // Reels & Posts (Bunny CDN)
        Route::get('/me/posts', [UserMediaController::class, 'myPosts']);
        Route::get('/me/reels', [UserMediaController::class, 'myReels']);
        Route::post('/reels/upload', [ReelController::class, 'upload']);
        Route::get('/reels/feed', [ReelsFeedController::class, 'feed']);
        Route::get('/reels/trending', [ReelsFeedController::class, 'trending']);
        Route::get('/reels/discover', [ReelsFeedController::class, 'discover']);
        Route::post('/posts/upload', [PostMediaController::class, 'upload']);
        Route::get('/posts/feed', [PostFeedController::class, 'feed']);
        Route::put('/posts/{id}', [UserMediaController::class, 'updatePost']);
        Route::delete('/posts/{id}', [UserMediaController::class, 'deletePost']);
        Route::post('/posts/{id}/like', [PostEngagementController::class, 'like']);
        Route::post('/posts/{id}/comment', [PostEngagementController::class, 'comment']);
        Route::get('/posts/{id}/comments', [PostEngagementController::class, 'getComments']);
        Route::post('/posts/{id}/save', [PostEngagementController::class, 'save']);
        Route::post('/posts/{id}/share', [PostEngagementController::class, 'share']);
        Route::put('/reels/{id}', [UserMediaController::class, 'updateReel']);
        Route::delete('/reels/{id}', [UserMediaController::class, 'deleteReel']);

        // Music library
        Route::get('/music/library', [MusicController::class, 'library']);
        Route::get('/music/trending', [MusicController::class, 'trending']);

        // User interaction (follow, friend, block)
        Route::post('/user/follow', [UserInteractionController::class, 'follow']);
        Route::post('/user/unfollow', [UserInteractionController::class, 'unfollow']);
        Route::get('/user/follow-requests', [UserInteractionController::class, 'followRequests']);
        Route::post('/user/accept-follow-request', [UserInteractionController::class, 'acceptFollowRequest']);
        Route::post('/user/reject-follow-request', [UserInteractionController::class, 'rejectFollowRequest']);
        Route::get('/user/friend-requests', [UserInteractionController::class, 'friendRequests']);
        Route::get('/user/friend-requests/count', [UserInteractionController::class, 'friendRequestsCount']);
        Route::post('/user/add-friend', [UserInteractionController::class, 'addFriend']);
        Route::post('/user/friend-request', [UserInteractionController::class, 'sendFriendRequest']);
        Route::post('/user/friend-request/accept', [UserInteractionController::class, 'acceptFriendRequestByRequest']);
        Route::post('/user/friend-request/decline', [UserInteractionController::class, 'declineFriendRequest']);
        Route::post('/user/accept-friend', [UserInteractionController::class, 'acceptFriend']);
        Route::post('/user/reject-friend', [UserInteractionController::class, 'rejectFriend']);
        Route::post('/user/block', [UserInteractionController::class, 'block']);
        Route::post('/user/unfriend', [UserInteractionController::class, 'unfriend']);
        Route::get('/user/{id}', [UserInteractionController::class, 'show']); // Alias for GET /users/{id}

        // Public profile relationship lists (followers, following, friends)
        Route::get('/users/{id}/followers', [UserInteractionController::class, 'followers']);
        Route::get('/users/{id}/following', [UserInteractionController::class, 'following']);
        Route::get('/users/{id}/friends', [UserInteractionController::class, 'friendsForUser']);

        // Device (FCM)
        Route::post('/device/register', [UserController::class, 'registerDevice']);
        Route::post('/update-fcm-token', [UserController::class, 'updateFcmToken']);

        // ---------------------------------------------------------------------
        // Reference data (cached: countries, languages, FAQ)
        // ---------------------------------------------------------------------
        Route::get('/countries', [CountryController::class, 'index']);
        Route::get('/languages', [LanguageController::class, 'index']);
        Route::get('/faq', [FaqController::class, 'index']);
        Route::post('/feedback', [FeedbackController::class, 'store']);

        // ---------------------------------------------------------------------
        // Rooms (list paginated)
        // ---------------------------------------------------------------------
        Route::prefix('rooms')->group(function () {
            Route::get('/', [RoomController::class, 'index']);
            Route::get('/themes', [RoomController::class, 'themes']);
            Route::post('/', [RoomController::class, 'store']);
            Route::get('/{roomId}', [RoomController::class, 'show']);
            Route::patch('/{roomId}', [RoomController::class, 'update']);
            Route::delete('/{roomId}', [RoomController::class, 'destroy']);
            Route::post('/{roomId}/join', [RoomController::class, 'join']);
            Route::post('/{roomId}/leave', [RoomController::class, 'leave']);
            Route::post('/{roomId}/heartbeat', [RoomController::class, 'heartbeat']);
            Route::post('/{roomId}/co-host', [RoomController::class, 'promoteToCoHost']);
            Route::post('/{roomId}/transfer-host', [RoomController::class, 'transferHost']);
            Route::get('/{roomId}/token', [RoomController::class, 'getToken']);
        });

        Route::prefix('rooms/{roomId}/seats')->group(function () {
            Route::get('/', [SeatController::class, 'index']);
            Route::post('/leave', [SeatController::class, 'leave']);
            Route::post('/{seatIndex}/take', [SeatController::class, 'take']);
            Route::post('/{seatIndex}/assign', [SeatController::class, 'assign']);
            Route::delete('/{seatIndex}', [SeatController::class, 'free']);
            Route::patch('/{seatIndex}/mute', [SeatController::class, 'mute']);
        });

        // Gifts (catalog cached; send not cached)
        Route::get('/gifts', [WalletController::class, 'gifts']);
        Route::get('/gift-types', [GiftController::class, 'index']);
        Route::post('/rooms/{roomId}/gifts/send', [GiftController::class, 'send']);
        Route::post('/gifts/send-batch', [GiftController::class, 'sendBatch']);

        // ---------------------------------------------------------------------
        // Wallet (packages/catalog cached; transactions/withdrawals paginated)
        // ---------------------------------------------------------------------
        Route::prefix('wallet')->group(function () {
            Route::get('/packages', [WalletController::class, 'packages']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
            Route::post('/recharge/initiate', [WalletController::class, 'initiateRecharge']);
            Route::post('/recharge/verify', [WalletController::class, 'verifyRecharge']);
            Route::post('/send-gift', [WalletController::class, 'sendGift']);
            Route::post('/transfer', [WalletController::class, 'transfer']);
            Route::post('/referral/convert', [WalletController::class, 'referralConvert']);
            Route::post('/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/withdrawals', [WalletController::class, 'withdrawals']);
            Route::get('/can-call/{receiver_id}/{call_type}', [WalletController::class, 'canCall']);
        });

        // Spin (prizes cached)
        Route::prefix('spin')->group(function () {
            Route::get('/prizes', [SpinController::class, 'prizes']);
            Route::post('/play', [SpinController::class, 'play']);
        });

        // Invite
        Route::get('/users/me/invite', [InviteController::class, 'me']);
        Route::post('/invite/apply', [InviteController::class, 'apply']);

        // ---------------------------------------------------------------------
        // Call (history paginated)
        // ---------------------------------------------------------------------
        Route::get('/call/token', [CallController::class, 'token']);
        Route::post('/call/initiate', [CallController::class, 'initiate']);
        Route::post('/call/accept', [CallController::class, 'accept']);
        Route::post('/call/reject', [CallController::class, 'reject']);
        Route::post('/call/end', [CallController::class, 'end']);
        Route::post('/call/heartbeat', [CallController::class, 'heartbeat']);
        Route::get('/call/active/{user_id}', [CallController::class, 'active']);
        Route::get('/call/status/{call_id}', [CallController::class, 'status']);
        Route::post('/call/status', [CallController::class, 'updateStatus']);
        Route::post('/call/log', [CallController::class, 'log']);
        Route::post('/agora/token', [CallController::class, 'agoraToken']);
        Route::get('/calls/history', [CallController::class, 'history']);

        // ---------------------------------------------------------------------
        // Chat & contacts (conversations/messages paginated)
        // ---------------------------------------------------------------------
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
        Route::post('/conversations/{id}/read', [ConversationController::class, 'markAsRead']);
        Route::get('/conversations/with-user/{userId}', [ContactController::class, 'conversationWithUser']);
        Route::get('/messages/{conversation_id}', [MessageController::class, 'index']);
        Route::post('/messages/upload-image', [MessageController::class, 'uploadImage']);
        Route::post('/messages/send', [MessageController::class, 'send']);
        Route::post('/messages/status', [MessageController::class, 'status']);
        Route::get('/contacts/friends', [ContactController::class, 'friends']);
        Route::post('/contacts/friends/add', [ContactController::class, 'addFriend']);
        Route::get('/contacts/groups', [ContactController::class, 'groups']);

        // Groups (members paginated)
        Route::post('/groups/create', [GroupController::class, 'store']);
        Route::patch('/groups/{groupId}', [GroupController::class, 'update']);
        Route::get('/groups/{groupId}/members', [GroupController::class, 'members']);
        Route::post('/groups/{groupId}/leave', [GroupController::class, 'leave']);
        Route::delete('/groups/{groupId}', [GroupController::class, 'destroy']);
        Route::delete('/groups/{groupId}/members', [GroupController::class, 'removeMember']);
        Route::post('/groups/{groupId}/members', [GroupController::class, 'addMembers']);

        // ---------------------------------------------------------------------
        // Level & profile frames (gamification; frames catalog cached)
        // ---------------------------------------------------------------------
        Route::post('/xp/add', [ProfileLevelController::class, 'addXp']);
        Route::get('/user/level', [ProfileLevelController::class, 'level']);
        Route::post('/user/level/add-xp', [ProfileLevelController::class, 'addXp']);
        Route::get('/profile/details', [ProfileLevelController::class, 'details']);
        Route::get('/profile/level', [ProfileLevelController::class, 'level']);
        Route::get('/profile/frames', [ProfileLevelController::class, 'frames']);
        Route::get('/profile/frames/all', [ProfileLevelController::class, 'allFrames']);
        Route::post('/profile/select-frame', [ProfileLevelController::class, 'selectFrame']);
    });
});
