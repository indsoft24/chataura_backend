<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InviteController extends Controller
{
    /**
     * Get user's invite information.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $totalInvited = $user->invitedUsers()->count();
        $totalEarnedCoins = $user->invitedUsers()->count() * 100; // 100 coins per invite (customize as needed)

        return ApiResponse::success([
            'invite_code' => $user->invite_code,
            'invite_link' => config('app.url') . '/invite/' . $user->invite_code,
            'reward_rules' => [
                'inviter_reward' => 100, // coins
                'referee_reward' => 50, // coins
            ],
            'total_invited' => $totalInvited,
            'total_earned_coins' => $totalEarnedCoins,
        ]);
    }

    /**
     * Apply invite code (called after registration).
     */
    public function apply(Request $request)
    {
        try {
            $validated = $request->validate([
                'invite_code' => 'required|string|exists:users,invite_code',
            ]);

            $user = $request->user();

            // Check if user already has an inviter
            if ($user->invited_by) {
                return ApiResponse::error('ALREADY_INVITED', 'You have already used an invite code', 400);
            }

            // Find inviter
            $inviter = \App\Models\User::where('invite_code', $validated['invite_code'])->first();

            if (!$inviter || $inviter->id === $user->id) {
                return ApiResponse::error('INVALID_INVITE_CODE', 'Invalid invite code', 400);
            }

            // Apply invite and rewards in one atomic transaction
            DB::transaction(function () use ($user, $inviter) {
                $inv = \App\Models\User::where('id', $inviter->id)->lockForUpdate()->first();
                $u = \App\Models\User::where('id', $user->id)->lockForUpdate()->first();
                if (!$inv || !$u) {
                    throw new \RuntimeException('User not found');
                }
                $u->invited_by = $inv->id;
                $u->save();

                // Referral/invite rewards: Gold Coins only (wallet_balance), not Gems.
                $inv->increment('wallet_balance', 100);
                $u->increment('wallet_balance', 50);
            });

            return ApiResponse::success([
                'message' => 'Invite code applied successfully',
                'reward_received' => 50,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('APPLY_INVITE_FAILED', $e->getMessage(), 500);
        }
    }
}

