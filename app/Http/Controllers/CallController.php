<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Call;
use App\Models\CallLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\AgoraService;
use App\Services\CallBillingService;
use App\Services\FirebaseCallService;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CallController extends Controller
{
    public function __construct(
        private AgoraService $agoraService,
        private FirebaseService $firebase,
        private FirebaseCallService $firebaseCall,
        private CallBillingService $callBillingService
    ) {}

    /**
     * GET /call/token - RTC token for 1-to-1 video or audio call.
     * Query: user_id (other user), type (video|audio).
     * Both callers use the same channel name (call_{minId}_{maxId}) so they join the same channel.
     */
    public function token(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'type' => 'required|string|in:video,audio',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $me = $request->user();
        $otherId = (int) $validated['user_id'];
        if ($otherId === $me->id) {
            return ApiResponse::error('INVALID_REQUEST', 'Cannot call yourself', 400);
        }

        $minId = min($me->id, $otherId);
        $maxId = max($me->id, $otherId);
        $channelName = "call_{$minId}_{$maxId}";

        $agoraUid = $this->agoraService->generateUid();
        $agoraToken = $this->agoraService->generateRtcTokenForChannel($channelName, $agoraUid);

        return ApiResponse::success([
            'agora_token' => $agoraToken,
            'channel_name' => $channelName,
            'agora_uid' => $agoraUid,
        ]);
    }

    /**
     * POST /agora/token - Agora RTC token by channel_name and user_id.
     * Body: channel_name (required), user_id (required, current user's DB id for mapping).
     * Returns: token, channel_name, uid.
     */
    public function agoraToken(Request $request)
    {
        try {
            $validated = $request->validate([
                'channel_name' => 'required|string|max:255',
                'user_id' => 'required|integer',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        if ((int) $validated['user_id'] !== $user->id) {
            return ApiResponse::forbidden('user_id must be the authenticated user');
        }
        $channelName = $validated['channel_name'];
        $uid = $this->agoraService->generateUid();
        $token = $this->agoraService->generateRtcTokenForChannel($channelName, $uid);
        return ApiResponse::success([
            'token' => $token,
            'channel_name' => $channelName,
            'uid' => $uid,
        ]);
    }

    /**
     * GET /calls/history - Call history for the authenticated user (caller or receiver).
     * Reads from the `calls` table. Paginated, ordered by created_at desc.
     * Query: page (default 1), limit (default 20, max 100).
     */
    public function history(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(max(1, (int) $request->query('limit', 20)), 100);

        $query = Call::where('caller_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['caller', 'receiver']);

        $total = $query->count();
        $calls = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $calls->map(function (Call $call) use ($user) {
            $isOutgoing = (int) $call->caller_id === (int) $user->id;
            $other = $isOutgoing ? $call->receiver : $call->caller;
            $durationSeconds = 0;
            if ($call->started_at && $call->ended_at) {
                $durationSeconds = (int) $call->started_at->diffInSeconds($call->ended_at);
            }

            return [
                'id' => $call->id,
                'call_type' => $call->call_type ?? 'audio',
                'status' => $call->status ?? 'ended',
                'is_outgoing' => $isOutgoing,
                'other_user' => $other ? [
                    'id' => $other->id,
                    'name' => $other->display_name ?? $other->name ?? 'User',
                    'avatar' => $other->avatar_url,
                    'level' => (int) ($other->level ?? 0),
                ] : null,
                'duration_seconds' => $durationSeconds,
                'timestamp' => $call->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
            ];
        })->values()->all();

        $meta = [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];

        return ApiResponse::success($data, $meta);
    }

    /**
     * POST /call/initiate - Start a 1-1 call. Creates call record, generates Agora token, sends FCM to receiver.
     * Body: conversation_id (optional), receiver_id (required), call_type (audio|video).
     * Prevents duplicate active call for caller or receiver. Receiver gets FCM to open IncomingCallActivity.
     */
    public function initiate(Request $request)
    {
        try {
            $validated = $request->validate([
                'conversation_id' => 'nullable|integer|exists:conversations,id',
                'receiver_id' => 'nullable|integer|exists:users,id',
                'call_type' => 'required|string|in:audio,video',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $receiverId = isset($validated['receiver_id']) ? (int) $validated['receiver_id'] : null;
        $conversationId = $validated['conversation_id'] ?? null;

        if ($receiverId === null && $conversationId) {
            $participant = ConversationParticipant::where('conversation_id', $conversationId)->where('user_id', $user->id)->first();
            if (!$participant) {
                return ApiResponse::forbidden('Not a participant of this conversation');
            }
            $receiverId = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('user_id', '!=', $user->id)
                ->value('user_id');
        }
        if (!$receiverId || $receiverId === $user->id) {
            return ApiResponse::error('INVALID_REQUEST', 'receiver_id is required and must be different from caller', 400);
        }

        // Terminate any stale accepted calls (no heartbeat for 90s) for caller and receiver before duplicate check
        $this->terminateStaleCallsForUser($user->id);
        $this->terminateStaleCallsForUser($receiverId);
        // Terminate stuck ringing calls (e.g. caller/receiver app crash) so both can call again
        $this->terminateStaleRingingCallsForUser($user->id);
        $this->terminateStaleRingingCallsForUser($receiverId);

        // Prevent duplicate active call (caller or receiver already in a call)
        $hasActive = Call::where(function ($q) use ($user, $receiverId) {
            $q->where('caller_id', $user->id)->orWhere('receiver_id', $user->id);
        })->whereIn('status', Call::ACTIVE_STATUSES)->exists();
        if ($hasActive) {
            return ApiResponse::error('ACTIVE_CALL_EXISTS', 'You or the receiver already have an active call', 409);
        }
        $hasActiveReceiver = Call::where(function ($q) use ($receiverId) {
            $q->where('caller_id', $receiverId)->orWhere('receiver_id', $receiverId);
        })->whereIn('status', Call::ACTIVE_STATUSES)->exists();
        if ($hasActiveReceiver) {
            return ApiResponse::error('ACTIVE_CALL_EXISTS', 'Receiver is already in a call', 409);
        }

        $channelName = 'call_' . min($user->id, $receiverId) . '_' . max($user->id, $receiverId) . '_' . time();
        $uid = $this->agoraService->generateUid();
        $token = $this->agoraService->generateRtcTokenForChannel($channelName, $uid);

        $call = Call::create([
            'caller_id' => $user->id,
            'receiver_id' => $receiverId,
            'channel_name' => $channelName,
            'agora_token' => $token,
            'call_type' => $validated['call_type'],
            'status' => Call::STATUS_RINGING,
        ]);

        $payload = [
            'call_id' => (string) $call->id,
            'conversation_id' => (string) ($conversationId ?? ''),
            'caller_id' => (string) $user->id,
            'caller_name' => $user->display_name ?? $user->name ?? 'User',
            'channel_name' => $channelName,
            'call_type' => $validated['call_type'],
            'token' => $token,
            'uid' => (string) $uid,
        ];
        if ($this->firebaseCall->isConfigured()) {
            $this->firebaseCall->sendIncomingCallNotification($receiverId, $payload);
        } else {
            $this->firebase->sendIncomingCallNotification($receiverId, $payload);
        }

        $receiver = User::find($receiverId);
        return ApiResponse::success([
            'call_id' => $call->id,
            'channel_name' => $channelName,
            'token' => $token,
            'uid' => $uid,
            'caller' => [
                'id' => $user->id,
                'name' => $user->display_name ?? $user->name,
                'avatar_url' => $user->avatar_url,
            ],
            'receiver' => $receiver ? [
                'id' => $receiver->id,
                'name' => $receiver->display_name ?? $receiver->name,
                'avatar_url' => $receiver->avatar_url,
            ] : null,
        ]);
    }

    /**
     * POST /call/accept - Accept incoming call. Only receiver can accept. Sets status = accepted, started_at.
     */
    public function accept(Request $request)
    {
        try {
            $validated = $request->validate([
                'call_id' => 'required|integer|exists:calls,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $call = Call::findOrFail($validated['call_id']);
        $user = $request->user();
        if ($call->receiver_id !== $user->id) {
            return ApiResponse::forbidden('Only the receiver can accept this call');
        }
        if ($call->status !== Call::STATUS_RINGING) {
            return ApiResponse::error('INVALID_STATE', 'Call is no longer active', 400);
        }
        $call->status = Call::STATUS_ACCEPTED;
        $call->started_at = now();
        $call->last_heartbeat_at = now();
        $call->save();

        $receiverUid = $this->agoraService->generateUid();
        $receiverToken = $this->agoraService->generateRtcTokenForChannel($call->channel_name, $receiverUid);

        return ApiResponse::success([
            'call_id' => $call->id,
            'status' => $call->status,
            'channel_name' => $call->channel_name,
            'token' => $receiverToken,
            'uid' => $receiverUid,
        ]);
    }

    /**
     * POST /call/reject - Reject incoming call. Participant (receiver or caller) can reject. Sets status = rejected.
     */
    public function reject(Request $request)
    {
        try {
            $validated = $request->validate([
                'call_id' => 'required|integer|exists:calls,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $call = Call::findOrFail($validated['call_id']);
        $user = $request->user();
        if ($call->receiver_id !== $user->id && $call->caller_id !== $user->id) {
            return ApiResponse::forbidden('Not a participant of this call');
        }
        if ($call->status !== Call::STATUS_RINGING) {
            return ApiResponse::error('INVALID_STATE', 'Call is no longer active', 400);
        }
        $call->status = Call::STATUS_REJECTED;
        $call->save();

        if ($this->firebaseCall->isConfigured()) {
            $this->firebaseCall->sendCallEndedToUser($call->caller_id, $call->id, 'call_ended');
        } elseif ($this->firebase->isConfigured()) {
            $caller = User::find($call->caller_id);
            if ($caller && !empty($caller->fcm_token)) {
                $this->firebase->sendToTokenDataOnly(
                    $caller->fcm_token,
                    ['type' => 'call_ended', 'call_id' => (string) $call->id],
                    'high'
                );
            }
        }

        return ApiResponse::success([
            'call_id' => $call->id,
            'status' => $call->status,
        ]);
    }

    /**
     * POST /call/end - End call. Participant can end. Expires Agora token (cleared in DB). Runs billing if accepted.
     */
    public function end(Request $request)
    {
        try {
            $validated = $request->validate([
                'call_id' => 'required|integer|exists:calls,id',
                'duration' => 'nullable|integer',
                'coins_deducted' => 'nullable|integer'
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $call = Call::findOrFail($validated['call_id']);
        $user = $request->user();
        if ($call->caller_id !== $user->id && $call->receiver_id !== $user->id) {
            return ApiResponse::forbidden('Not a participant of this call');
        }

        $endedAt = now();
        $isCaller = $user->id === $call->caller_id;
        $coinsDeducted = (int) $request->input('coins_deducted', 0);

        DB::beginTransaction();
        try {
            // 1) Trust caller payload: if caller sends coins_deducted > 0, bill that amount (even if call already ended by receiver).
            if ($isCaller && $coinsDeducted > 0) {
                $this->callBillingService->runBillingFromPayload($call, $coinsDeducted);
            }
            // 2) Mark call ended (idempotent; receiver may have already set it).
            if ($call->status !== Call::STATUS_ENDED) {
                $call->status = Call::STATUS_ENDED;
                $call->ended_at = $endedAt;
                $call->agora_token = null;
                $call->save();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $call->update(['status' => Call::STATUS_ENDED, 'ended_at' => $endedAt, 'agora_token' => null]);
        }

        $otherUserId = $user->id === $call->caller_id ? $call->receiver_id : $call->caller_id;
        if ($this->firebaseCall->isConfigured()) {
            $this->firebaseCall->sendCallEndedToUser($otherUserId, $call->id, 'call_ended');
        } elseif ($this->firebase->isConfigured()) {
            $other = User::find($otherUserId);
            if ($other && !empty($other->fcm_token)) {
                $this->firebase->sendToTokenDataOnly(
                    $other->fcm_token,
                    ['type' => 'call_ended', 'call_id' => (string) $call->id],
                    'high'
                );
            }
        }

        $user->refresh();
        return ApiResponse::success([
            'call_id' => $call->id,
            'status' => $call->status,
            'ended_at' => $call->ended_at->toIso8601String(),
            'current_balance' => (int) $user->wallet_balance,
        ]);
    }

    /**
     * POST /call/heartbeat - Update last_heartbeat_at for an accepted call. Client calls every ~30s.
     */
    public function heartbeat(Request $request)
    {
        try {
            $validated = $request->validate(['call_id' => 'required|integer|exists:calls,id']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $call = Call::findOrFail($validated['call_id']);
        $user = $request->user();
        if ($call->caller_id !== $user->id && $call->receiver_id !== $user->id) {
            return ApiResponse::forbidden('Not a participant of this call');
        }
        if ($call->status !== Call::STATUS_ACCEPTED) {
            return ApiResponse::error('INVALID_STATE', 'Call is not active', 400);
        }
        $call->last_heartbeat_at = now();
        $call->save();
        return ApiResponse::success([
            'call_id' => $call->id,
            'last_heartbeat_at' => $call->last_heartbeat_at->toIso8601String(),
        ]);
    }

    /** Ringing calls older than this (seconds) are marked missed so users can call again after crash. */
    private const STALE_RINGING_SECONDS = 60;

    /**
     * Mark stuck ringing calls (e.g. app crash) as missed for this user so they can receive/make calls again.
     * Called from initiate() before duplicate check.
     */
    public function terminateStaleRingingCallsForUser(int $userId): void
    {
        $threshold = now()->subSeconds(self::STALE_RINGING_SECONDS);
        Call::where(function ($q) use ($userId) {
            $q->where('caller_id', $userId)->orWhere('receiver_id', $userId);
        })
            ->where('status', Call::STATUS_RINGING)
            ->where('created_at', '<', $threshold)
            ->update([
                'status' => Call::STATUS_MISSED,
                'ended_at' => now(),
                'agora_token' => null,
            ]);
    }

    /**
     * Find and terminate stale accepted calls for the given user(s). Used by initiate and by scheduled command.
     */
    public function terminateStaleCallsForUser(int $userId): void
    {
        $threshold = now()->subSeconds(90);
        $stale = Call::where(function ($q) use ($userId) {
            $q->where('caller_id', $userId)->orWhere('receiver_id', $userId);
        })
            ->where('status', Call::STATUS_ACCEPTED)
            ->where(function ($q) use ($threshold) {
                $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $threshold);
            })
            ->get();

        foreach ($stale as $call) {
            $endedAt = $call->last_heartbeat_at ?? $call->started_at ?? now();
            $endedAt = $endedAt instanceof Carbon ? $endedAt : Carbon::parse($endedAt);
            try {
                $this->callBillingService->terminateCallAt($call, $endedAt);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Stale call terminate billing error: ' . $e->getMessage(), ['call_id' => $call->id]);
                $call->update(['status' => Call::STATUS_ENDED, 'ended_at' => $endedAt, 'agora_token' => null]);
            }
            $otherId = $call->caller_id === $userId ? $call->receiver_id : $call->caller_id;
            if ($this->firebaseCall->isConfigured()) {
                $this->firebaseCall->sendCallEndedToUser($otherId, $call->id, 'call_ended');
            } elseif ($this->firebase->isConfigured()) {
                $other = User::find($otherId);
                if ($other && !empty($other->fcm_token)) {
                    $this->firebase->sendToTokenDataOnly($other->fcm_token, ['type' => 'call_ended', 'call_id' => (string) $call->id], 'high');
                }
            }
        }
    }

    /**
     * GET /call/active/{user_id} - Return active incoming call for the user (status ringing or accepted).
     * user_id must be the authenticated user. Clears stale ringing first so ghost calls don't appear.
     */
    public function active(Request $request, int $user_id)
    {
        $user = $request->user();
        if ((int) $user_id !== $user->id) {
            return ApiResponse::forbidden('Can only fetch own active call');
        }
        $this->terminateStaleRingingCallsForUser($user_id);
        $this->terminateStaleCallsForUser($user_id);

        $call = Call::where('receiver_id', $user_id)
            ->whereIn('status', Call::ACTIVE_STATUSES)
            ->with('caller')
            ->orderBy('created_at', 'desc')
            ->first();
        if (!$call) {
            return ApiResponse::success(['active_call' => null]);
        }
        $caller = $call->caller;
        $receiverUid = $this->agoraService->generateUid();
        $receiverToken = $this->agoraService->generateRtcTokenForChannel($call->channel_name, $receiverUid);

        return ApiResponse::success([
            'active_call' => [
                'call_id' => $call->id,
                'channel_name' => $call->channel_name,
                'token' => $receiverToken,
                'call_type' => $call->call_type,
                'status' => $call->status,
                'caller' => $caller ? [
                    'id' => $caller->id,
                    'name' => $caller->display_name ?? $caller->name,
                    'avatar_url' => $caller->avatar_url,
                ] : null,
            ],
        ]);
    }

    /**
     * GET /call/status/{id} - Current call status. Only caller or receiver can fetch. 404 if not found.
     */
    public function status(Request $request, int $call_id)
    {
        $call = Call::find($call_id);
        if (!$call) {
            return ApiResponse::error('NOT_FOUND', 'Call not found', 404);
        }
        $user = $request->user();
        if ($call->caller_id !== $user->id && $call->receiver_id !== $user->id) {
            return ApiResponse::forbidden('Not a participant of this call');
        }
        return response()->json([
            'success' => true,
            'message' => 'Call status',
            'data' => [
                'call_id' => $call->id,
                'status' => $call->status,
            ],
        ]);
    }

    /**
     * POST /call/status - Update call status (ringing, answered, rejected, missed, ended).
     * Body: call_id (required), status (required).
     */
    public function updateStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'call_id' => 'required|integer|exists:call_logs,id',
                'status' => 'required|string|in:initiated,ringing,answered,rejected,missed,ended',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $callLog = CallLog::findOrFail($validated['call_id']);
        $user = $request->user();
        if ($callLog->caller_id !== $user->id && $callLog->receiver_id !== $user->id) {
            return ApiResponse::forbidden('Not a participant of this call');
        }
        $callLog->status = $validated['status'];
        if ($validated['status'] === CallLog::STATUS_ENDED) {
            $callLog->ended_at = now();
        }
        $callLog->save();
        return ApiResponse::success([
            'call_id' => $callLog->id,
            'status' => $callLog->status,
            'ended_at' => $callLog->ended_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /call/log - Store call history (missed, completed, rejected).
     * Body: receiver_id, channel_name, call_type (audio|video), status (missed|completed|rejected).
     */
    public function log(Request $request)
    {
        try {
            $validated = $request->validate([
                'receiver_id' => 'required|integer|exists:users,id',
                'channel_name' => 'required|string|max:255',
                'call_type' => 'required|string|in:audio,video',
                'status' => 'required|string|in:missed,completed,rejected',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }
        $user = $request->user();
        $log = CallLog::create([
            'caller_id' => $user->id,
            'receiver_id' => $validated['receiver_id'],
            'channel_name' => $validated['channel_name'],
            'call_type' => $validated['call_type'],
            'status' => $validated['status'],
        ]);
        return ApiResponse::success([
            'id' => $log->id,
            'status' => $log->status,
        ]);
    }
}
