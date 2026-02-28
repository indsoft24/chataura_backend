<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\Call;
use App\Models\CoinTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Runs billing for an ended call: deduct from caller wallet, credit receiver gems, platform share.
 * Used by call/end (payload), stale-call cleanup, and initiate (when auto-terminating stale calls).
 */
class CallBillingService
{
    public function runBilling(Call $call, Carbon $endedAt): void
    {
        if ($call->status !== Call::STATUS_ACCEPTED || !$call->started_at) {
            return;
        }

        $settings = AdminSetting::first();
        if (!$settings) {
            return;
        }

        $durationSeconds = max(0, (int) $endedAt->diffInSeconds($call->started_at));
        $isVideo = $call->call_type === Call::TYPE_VIDEO;
        $pricePerMin = $isVideo ? (int) ($settings->video_call_price_per_min ?? 0) : (int) ($settings->audio_call_price_per_min ?? 0);
        $commissionPercent = $isVideo ? (int) ($settings->video_call_commission_percent ?? 0) : (int) ($settings->audio_call_commission_percent ?? 0);

        if ($pricePerMin <= 0) {
            return;
        }

        $billedMinutes = (int) ceil($durationSeconds / 60.0);
        $totalBilled = $billedMinutes * $pricePerMin;

        if ($totalBilled <= 0) {
            return;
        }

        DB::transaction(function () use ($call, $totalBilled, $commissionPercent, $isVideo) {
            $caller = User::where('id', $call->caller_id)->lockForUpdate()->first();
            $receiver = User::where('id', $call->receiver_id)->lockForUpdate()->first();
            $systemUserId = config('admin.system_user_id', 1);
            $systemUser = User::where('id', $systemUserId)->lockForUpdate()->first();

            if (!$caller || !$receiver || ((int) ($caller->wallet_balance ?? 0)) < $totalBilled) {
                return;
            }

            $adminShare = (int) floor(($totalBilled * $commissionPercent) / 100);
            $receiverShare = $totalBilled - $adminShare;

            $caller->decrement('wallet_balance', $totalBilled);
            $receiver->increment('gems', $receiverShare);
            $receiver->increment('total_earned_coins', $receiverShare);
            if ($systemUser) {
                $systemUser->increment('wallet_balance', $adminShare);
            }

            CoinTransaction::create([
                'sender_id' => $call->caller_id,
                'receiver_id' => $call->receiver_id,
                'transaction_type' => $isVideo ? CoinTransaction::TYPE_VIDEO_CALL : CoinTransaction::TYPE_AUDIO_CALL,
                'reference_id' => $call->id,
                'gross_coins_deducted' => $totalBilled,
                'admin_commission_coins' => $adminShare,
                'net_coins_received' => $receiverShare,
            ]);
            CoinTransaction::create([
                'sender_id' => $call->caller_id,
                'receiver_id' => $systemUserId,
                'transaction_type' => CoinTransaction::TYPE_CALL_COMMISSION,
                'reference_id' => $call->id,
                'gross_coins_deducted' => 0,
                'admin_commission_coins' => 0,
                'net_coins_received' => $adminShare,
            ]);
        });
    }

    /**
     * Bill using the caller's payload amount (coins_deducted). Used when caller hits call/end;
     * avoids race (call may already be ended by receiver) and trusts Android amount.
     * Idempotent if already billed for this call.
     */
    public function runBillingFromPayload(Call $call, int $coinsDeducted): void
    {
        if ($coinsDeducted <= 0) {
            return;
        }

        $isVideo = $call->call_type === Call::TYPE_VIDEO;
        $txType = $isVideo ? CoinTransaction::TYPE_VIDEO_CALL : CoinTransaction::TYPE_AUDIO_CALL;
        if (CoinTransaction::where('reference_id', $call->id)->where('transaction_type', $txType)->exists()) {
            return;
        }

        $settings = AdminSetting::first();
        if (!$settings) {
            throw new \RuntimeException('Admin settings missing');
        }

        $commissionPercent = $isVideo
            ? (int) ($settings->video_call_commission_percent ?? 0)
            : (int) ($settings->audio_call_commission_percent ?? 0);
        $totalBilled = $coinsDeducted;

        DB::transaction(function () use ($call, $totalBilled, $commissionPercent, $isVideo, $txType) {
            $caller = User::where('id', $call->caller_id)->lockForUpdate()->first();
            $receiver = User::where('id', $call->receiver_id)->lockForUpdate()->first();
            $systemUserId = config('admin.system_user_id', 1);
            $systemUser = User::where('id', $systemUserId)->lockForUpdate()->first();

            if (!$caller || !$receiver) {
                throw new \RuntimeException('Caller or receiver not found');
            }

            if ((int) ($caller->wallet_balance ?? 0) < $totalBilled) {
                throw new \RuntimeException('Insufficient caller wallet balance');
            }

            $adminShare = (int) floor(($totalBilled * $commissionPercent) / 100);
            $receiverShare = $totalBilled - $adminShare;

            $caller->decrement('wallet_balance', $totalBilled);
            $receiver->increment('gems', $receiverShare);
            $receiver->increment('total_earned_coins', $receiverShare);
            if ($systemUser) {
                $systemUser->increment('wallet_balance', $adminShare);
            }

            CoinTransaction::create([
                'sender_id' => $call->caller_id,
                'receiver_id' => $call->receiver_id,
                'transaction_type' => $txType,
                'reference_id' => $call->id,
                'gross_coins_deducted' => $totalBilled,
                'admin_commission_coins' => $adminShare,
                'net_coins_received' => $receiverShare,
            ]);
            CoinTransaction::create([
                'sender_id' => $call->caller_id,
                'receiver_id' => $systemUserId,
                'transaction_type' => CoinTransaction::TYPE_CALL_COMMISSION,
                'reference_id' => $call->id,
                'gross_coins_deducted' => 0,
                'admin_commission_coins' => 0,
                'net_coins_received' => $adminShare,
            ]);
        });
    }

    /**
     * Mark call as ended at the given time and run billing. Used by stale-call cleanup and initiate safety.
     */
    public function terminateCallAt(Call $call, Carbon $endedAt): void
    {
        $call->status = Call::STATUS_ENDED;
        $call->ended_at = $endedAt;
        $call->agora_token = null;
        $call->save();
        $this->runBilling($call, $endedAt);
    }
}
