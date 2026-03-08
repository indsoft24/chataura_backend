<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AdminSetting;
use App\Models\ReferralHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InviteController extends Controller
{
    /**
     * Get user's invite information. Reward amounts from AdminSetting.
     * referral_balance is shown in /users/me and /user/profile.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $settings = AdminSetting::get();
        $totalInvited = $user->invitedUsers()->count();
        $referrerReward = (int) $settings->referral_reward_referrer;
        $refereeReward = (int) $settings->referral_reward_referee;

        return ApiResponse::success([
            'invite_code' => $user->invite_code,
            'invite_link' => config('app.url') . '/invite/' . $user->invite_code,
            'referral_balance' => (int) ($user->referral_balance ?? 0),
            'reward_rules' => [
                'inviter_reward' => $referrerReward,
                'referee_reward' => $refereeReward,
            ],
            'total_invited' => $totalInvited,
        ]);
    }

    /**
     * Apply invite code (called after registration). Credits referral_balance for both referrer and referee.
     */
    public function apply(Request $request)
    {
        try {
            $validated = $request->validate([
                'invite_code' => 'required|string|exists:users,invite_code',
            ]);

            $user = $request->user();

            if ($user->invited_by) {
                return ApiResponse::error('ALREADY_INVITED', 'You have already used an invite code', 400);
            }

            $inviter = User::where('invite_code', $validated['invite_code'])->first();

            if (!$inviter || (int) $inviter->id === (int) $user->id) {
                return ApiResponse::error('INVALID_INVITE_CODE', 'Invalid invite code', 400);
            }

            $settings = AdminSetting::get();
            $rewardReferrer = (int) $settings->referral_reward_referrer;
            $rewardReferee = (int) $settings->referral_reward_referee;

            DB::transaction(function () use ($user, $inviter, $rewardReferrer, $rewardReferee) {
                $inv = User::where('id', $inviter->id)->lockForUpdate()->first();
                $u = User::where('id', $user->id)->lockForUpdate()->first();
                if (!$inv || !$u) {
                    throw new \RuntimeException('User not found');
                }
                if ($u->invited_by) {
                    throw new \RuntimeException('ALREADY_INVITED');
                }
                $u->invited_by = $inv->id;
                $u->save();

                $inv->increment('referral_balance', $rewardReferrer);
                $u->increment('referral_balance', $rewardReferee);

                ReferralHistory::create([
                    'referrer_id' => $inv->id,
                    'referee_id' => $u->id,
                    'referrer_amount' => $rewardReferrer,
                    'referee_amount' => $rewardReferee,
                ]);
            });

            return ApiResponse::success([
                'message' => 'Invite code applied successfully',
                'reward_received' => $rewardReferee,
                'referral_balance' => (int) ($user->refresh()->referral_balance ?? 0),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'ALREADY_INVITED') {
                return ApiResponse::error('ALREADY_INVITED', 'You have already used an invite code', 400);
            }
            return ApiResponse::error('APPLY_INVITE_FAILED', $e->getMessage(), 500);
        } catch (\Exception $e) {
            return ApiResponse::error('APPLY_INVITE_FAILED', $e->getMessage(), 500);
        }
    }
}

