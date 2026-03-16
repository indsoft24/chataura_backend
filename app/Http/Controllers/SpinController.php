<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Services\ApiCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpinController extends Controller
{
    /**
     * GET /api/v1/spin/prizes – current prize table. Cached (short TTL).
     */
    public function prizes(Request $request, ApiCacheService $cache)
    {
        $ttl = $cache->ttl('spin');
        $data = $cache->remember($cache->versionedKey('spin_prizes'), $ttl, function () {
            $prizes = collect(config('spin.prizes', []))->map(function ($p) {
                return [
                    'label' => $p['label'],
                    'emoji' => $p['emoji'] ?? '🪙',
                    'coins' => (int) ($p['coins'] ?? 0),
                    'probability' => (float) ($p['probability'] ?? 0),
                ];
            })->values()->all();
            return [
                'spin_cost' => (int) config('spin.spin_cost', 10),
                'prizes' => $prizes,
            ];
        });
        return $cache->applyHttpCacheHeaders($request, ApiResponse::success($data), $ttl, 'public');
    }

    /**
     * POST /api/v1/spin/play – play one spin. Server-side random only; never trust client.
     * Body: { "room_id": "string" } (optional, for room context).
     */
    public function play(Request $request)
    {
        $spinCost = (int) config('spin.spin_cost', 10);
        $user = $request->user();

        if ((int) $user->wallet_balance < $spinCost) {
            return ApiResponse::error('INSUFFICIENT_BALANCE', 'You need at least ' . $spinCost . ' coins to spin', 400);
        }

        $prizes = config('spin.prizes', []);
        if (empty($prizes)) {
            return ApiResponse::error('CONFIG_ERROR', 'Spin prizes not configured', 503);
        }

        try {
            $result = DB::transaction(function () use ($user, $spinCost, $prizes) {
                $u = User::where('id', $user->id)->lockForUpdate()->first();
                if (!$u || (int) $u->wallet_balance < $spinCost) {
                    return null;
                }
                $u->decrement('wallet_balance', $spinCost);

                $prize = $this->selectPrize($prizes);
                $credit = (int) ($prize['coins'] ?? 0);
                if ($credit > 0) {
                    $u->increment('wallet_balance', $credit);
                }

                $u->refresh();
                return [
                    'prize_type' => $prize['type'] ?? 'loss',
                    'prize_value' => $credit,
                    'gift_name' => $prize['gift_name'] ?? null,
                    'new_balance' => (int) $u->wallet_balance,
                ];
            });
        } catch (\Throwable $e) {
            return ApiResponse::error('SPIN_ERROR', 'Spin failed. Please try again.', 500);
        }

        if ($result === null) {
            return ApiResponse::error('INSUFFICIENT_BALANCE', 'You need at least ' . $spinCost . ' coins to spin', 400);
        }

        return ApiResponse::success($result);
    }

    /**
     * Weighted random selection from prizes. Server-side only.
     */
    private function selectPrize(array $prizes): array
    {
        $r = mt_rand(1, 10000) / 10000.0;
        $cum = 0.0;
        foreach ($prizes as $p) {
            $cum += (float) ($p['probability'] ?? 0);
            if ($r <= $cum) {
                return $p;
            }
        }
        return $prizes[array_key_last($prizes)];
    }
}
