<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Services\FirebaseCallService;
use App\Services\FirebaseService;
use Illuminate\Console\Command;

/**
 * Marks calls that have been ringing for more than 60 seconds as missed,
 * clears token and ended_at, and sends FCM to the caller. Run every minute via scheduler.
 * Prevents stuck "ringing" state when caller/receiver app crashes or network fails.
 */
class MarkMissedCallsCommand extends Command
{
    /** Ring timeout (seconds) after which we mark as missed so both users can call again. */
    private const RING_TIMEOUT_SECONDS = 60;

    protected $signature = 'call:mark-missed';

    protected $description = 'Mark ringing calls older than 60 seconds as missed, clear token, and notify caller';

    public function __construct(
        private FirebaseCallService $firebaseCall,
        private FirebaseService $firebase
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = now()->subSeconds(self::RING_TIMEOUT_SECONDS);
        $calls = Call::where('status', Call::STATUS_RINGING)
            ->where('created_at', '<', $threshold)
            ->get();

        foreach ($calls as $call) {
            $call->status = Call::STATUS_MISSED;
            $call->ended_at = now();
            $call->agora_token = null;
            $call->save();

            if ($this->firebaseCall->isConfigured()) {
                $this->firebaseCall->sendCallEndedToUser($call->caller_id, $call->id, 'call_missed');
            } elseif ($this->firebase->isConfigured()) {
                $caller = $call->caller;
                if ($caller && !empty($caller->fcm_token)) {
                    $this->firebase->sendToTokenDataOnly(
                        $caller->fcm_token,
                        ['type' => 'call_missed', 'call_id' => (string) $call->id],
                        'high'
                    );
                }
            }
        }

        if ($calls->isNotEmpty()) {
            $this->info('Marked ' . $calls->count() . ' call(s) as missed.');
        }

        return self::SUCCESS;
    }
}
