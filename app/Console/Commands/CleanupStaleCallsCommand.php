<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\User;
use App\Services\CallBillingService;
use App\Services\FirebaseCallService;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Terminates accepted calls with no heartbeat for 90+ seconds (ghost calls).
 * Runs billing up to last_heartbeat_at, expires token, notifies other participant.
 * Schedule: every 1–5 minutes (e.g. everyMinute()).
 */
class CleanupStaleCallsCommand extends Command
{
    protected $signature = 'call:cleanup-stale';

    protected $description = 'End accepted calls with last_heartbeat_at older than 90 seconds; run billing and notify';

    public function __construct(
        private CallBillingService $callBillingService,
        private FirebaseCallService $firebaseCall,
        private FirebaseService $firebase
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = now()->subSeconds(90);
        $stale = Call::where('status', Call::STATUS_ACCEPTED)
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
                $this->error("Call {$call->id} billing failed: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error('Stale call cleanup billing error: ' . $e->getMessage(), ['call_id' => $call->id]);
                $call->update(['status' => Call::STATUS_ENDED, 'ended_at' => $endedAt, 'agora_token' => null]);
            }

            $callerId = $call->caller_id;
            $receiverId = $call->receiver_id;
            if ($this->firebaseCall->isConfigured()) {
                $this->firebaseCall->sendCallEndedToUser($callerId, $call->id, 'call_ended');
                $this->firebaseCall->sendCallEndedToUser($receiverId, $call->id, 'call_ended');
            } elseif ($this->firebase->isConfigured()) {
                foreach ([$callerId, $receiverId] as $uid) {
                    $u = User::find($uid);
                    if ($u && !empty($u->fcm_token)) {
                        $this->firebase->sendToTokenDataOnly($u->fcm_token, ['type' => 'call_ended', 'call_id' => (string) $call->id], 'high');
                    }
                }
            }
        }

        if ($stale->isNotEmpty()) {
            $this->info('Terminated ' . $stale->count() . ' stale call(s).');
        }

        return self::SUCCESS;
    }
}
