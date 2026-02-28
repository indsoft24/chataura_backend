<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\ProfileLevelController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\SpinController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInteractionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Base URL: https://chataura.indsoft24.com/api/v1
|
*/

Route::prefix('v1')->group(function () {
    // Public routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'google']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Protected routes
    Route::middleware(['auth.api'])->group(function () {
        // Email OTP (after email registration)
        Route::prefix('auth')->group(function () {
            Route::post('/send-email-otp', [AuthController::class, 'sendEmailOtp']);
            Route::post('/verify-email-otp', [AuthController::class, 'verifyEmailOtp']);
        });

        // User routes (plural: /users/me, /users/{id})
        Route::prefix('users')->group(function () {
            Route::get('/me', [UserController::class, 'me']);
            Route::patch('/me', [UserController::class, 'updateMe']);
            Route::get('/me/wallet', [UserController::class, 'wallet']);
            Route::get('/me/balance', [UserController::class, 'wallet']); // Alias
            Route::get('/me/transactions', [UserController::class, 'transactions']);
            Route::get('/search', [UserController::class, 'search']);
            Route::get('/{id}/followers', [UserInteractionController::class, 'followers']);
            Route::get('/{id}/following', [UserInteractionController::class, 'following']);
            Route::get('/{id}/gifts', [UserInteractionController::class, 'gifts']);
            Route::get('/{id}/privileges', [UserInteractionController::class, 'privileges']);
            Route::get('/{id}', [UserInteractionController::class, 'show']); // Public profile by DB id (integer)
        });

        // Room routes
        Route::prefix('rooms')->group(function () {
            Route::get('/', [RoomController::class, 'index']);
            Route::get('/themes', [RoomController::class, 'themes']);
            Route::post('/', [RoomController::class, 'store']);
            Route::get('/{roomId}', [RoomController::class, 'show']);
            Route::patch('/{roomId}', [RoomController::class, 'update']);
            Route::delete('/{roomId}', [RoomController::class, 'destroy']);
            Route::post('/{roomId}/join', [RoomController::class, 'join']);
            Route::post('/{roomId}/leave', [RoomController::class, 'leave']);
            Route::post('/{roomId}/transfer-host', [RoomController::class, 'transferHost']);
            Route::get('/{roomId}/token', [RoomController::class, 'getToken']);
        });

        // Seat routes
        Route::prefix('rooms/{roomId}/seats')->group(function () {
            Route::get('/', [SeatController::class, 'index']);
            Route::post('/leave', [SeatController::class, 'leave']);
            Route::post('/{seatIndex}/take', [SeatController::class, 'take']);
            Route::post('/{seatIndex}/assign', [SeatController::class, 'assign']);
            Route::delete('/{seatIndex}', [SeatController::class, 'free']);
            Route::patch('/{seatIndex}/mute', [SeatController::class, 'mute']);
        });

        // Virtual gifts (wallet send-gift): GET /api/v1/gifts per spec
        Route::get('/gifts', [WalletController::class, 'gifts']);
        // Room gift types (catalog for rooms): GET /api/v1/gift-types
        Route::get('/gift-types', [GiftController::class, 'index']);
        Route::post('/rooms/{roomId}/gifts/send', [GiftController::class, 'send']);
        Route::post('/gifts/send-batch', [GiftController::class, 'sendBatch']);

        // Wallet (packages, recharge, send-gift, can-call)
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

        // Lucky Spin (party room)
        Route::prefix('spin')->group(function () {
            Route::get('/prizes', [SpinController::class, 'prizes']);
            Route::post('/play', [SpinController::class, 'play']);
        });

        // Invite routes
        Route::prefix('users/me/invite')->group(function () {
            Route::get('/', [InviteController::class, 'me']);
        });
        Route::post('/invite/apply', [InviteController::class, 'apply']);

        // ---- Device (FCM) ----
        Route::post('/device/register', [UserController::class, 'registerDevice']);
        Route::post('/update-fcm-token', [UserController::class, 'updateFcmToken']);

        // ---- 1-to-1 call (video/audio) & Agora ----
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

        // ---- Chat ----
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
        Route::post('/groups/create', [GroupController::class, 'store']);
        Route::patch('/groups/{groupId}', [GroupController::class, 'update']);
        Route::get('/groups/{groupId}/members', [GroupController::class, 'members']);
        Route::post('/groups/{groupId}/leave', [GroupController::class, 'leave']);
        Route::delete('/groups/{groupId}', [GroupController::class, 'destroy']);
        Route::delete('/groups/{groupId}/members', [GroupController::class, 'removeMember']);
        Route::post('/groups/{groupId}/members', [GroupController::class, 'addMembers']);

        // ---- Level & profile frames (gamification) ----
        Route::post('/xp/add', [ProfileLevelController::class, 'addXp']);
        Route::get('/user/level', [ProfileLevelController::class, 'level']);
        Route::post('/user/level/add-xp', [ProfileLevelController::class, 'addXp']);
        Route::get('/profile/details', [ProfileLevelController::class, 'details']);
        Route::get('/profile/level', [ProfileLevelController::class, 'level']);
        Route::get('/profile/frames', [ProfileLevelController::class, 'frames']);
        Route::get('/profile/frames/all', [ProfileLevelController::class, 'allFrames']);
        Route::post('/profile/select-frame', [ProfileLevelController::class, 'selectFrame']);

        // ---- Profile & settings ----
        Route::get('/user/profile', [UserController::class, 'profile']);
        Route::post('/user/update', [UserController::class, 'update']);
        Route::post('/upload', [UploadController::class, 'upload']);
        Route::get('/languages', [LanguageController::class, 'index']);
        Route::post('/user/update-language', [UserController::class, 'updateLanguage']);
        Route::get('/countries', [CountryController::class, 'index']);
        Route::get('/faq', [FaqController::class, 'index']);
        Route::post('/feedback', [FeedbackController::class, 'store']);
        Route::get('/user/blocked-users', [UserController::class, 'blockedUsers']);
        Route::post('/user/unblock', [UserController::class, 'unblock']);
        Route::post('/user/device', [UserController::class, 'registerDevice']);
        Route::post('/user/delete', [UserController::class, 'delete']);
        Route::post('/user/privacy', [UserController::class, 'privacy']);
        Route::post('/user/notifications', [UserController::class, 'notifications']);

        // ---- User interaction (follow, friend, block) ----
        Route::post('/user/follow', [UserInteractionController::class, 'follow']);
        Route::post('/user/unfollow', [UserInteractionController::class, 'unfollow']);
        Route::get('/user/follow-requests', [UserInteractionController::class, 'followRequests']);
        Route::post('/user/accept-follow-request', [UserInteractionController::class, 'acceptFollowRequest']);
        Route::post('/user/reject-follow-request', [UserInteractionController::class, 'rejectFollowRequest']);
        Route::get('/user/friend-requests', [UserInteractionController::class, 'friendRequests']);
        Route::post('/user/add-friend', [UserInteractionController::class, 'addFriend']);
        Route::post('/user/friend-request', [UserInteractionController::class, 'sendFriendRequest']);
        Route::post('/user/friend-request/accept', [UserInteractionController::class, 'acceptFriendRequestByRequest']);
        Route::post('/user/friend-request/decline', [UserInteractionController::class, 'declineFriendRequest']);
        Route::post('/user/accept-friend', [UserInteractionController::class, 'acceptFriend']);
        Route::post('/user/reject-friend', [UserInteractionController::class, 'rejectFriend']);
        Route::post('/user/block', [UserInteractionController::class, 'block']);
        Route::get('/user/{id}', [UserInteractionController::class, 'show']);
    });
});

