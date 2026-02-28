<?php

namespace App\Services;

use App\Models\Frame;
use App\Models\Level;
use App\Models\User;
use App\Models\UserUnlockedFrame;
use Illuminate\Support\Facades\DB;

class LevelService
{
    /**
     * Add XP to a user, recalculate level, and unlock any newly eligible frames.
     *
     * @return array{level_up: bool, level: int, xp: int, xp_progress_pct: float, level_min_xp: int, level_max_xp: int}
     */
    public function addXp(int $userId, int $amount): array
    {
        if ($amount <= 0) {
            $user = User::findOrFail($userId);
            return $this->levelPayload($user);
        }

        $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
        $oldLevelId = (int) $user->level;

        $user->xp = max(0, (int) $user->xp + $amount);
        $user->save();

        $levelRow = $this->levelForXp($user->xp);
        $newLevelId = $levelRow ? (int) $levelRow->id : 0;

        $levelUp = $newLevelId > $oldLevelId;
        if ($levelUp) {
            $user->level = $newLevelId;
            $user->save();
            $this->unlockFramesForLevel($userId, $newLevelId);
        }

        $user->refresh();
        $payload = $this->levelPayload($user);
        $payload['level_up'] = $levelUp;
        return $payload;
    }

    /**
     * Get the level row that contains the given XP (0–10).
     */
    public function levelForXp(int $xp): ?Level
    {
        return Level::where('min_xp', '<=', $xp)
            ->where('max_xp', '>=', $xp)
            ->first();
    }

    /**
     * When user level changes: unlock all active frames with level_required <= $levelId.
     * Inserts into user_unlocked_frames (user_id, frame_id, unlocked_at). Avoids duplicates via firstOrCreate.
     */
    public function unlockFramesForLevel(int $userId, int $levelId): void
    {
        $frameIds = Frame::active()
            ->where('level_required', '<=', $levelId)
            ->where('is_premium', false)
            ->pluck('id');

        foreach ($frameIds as $frameId) {
            UserUnlockedFrame::firstOrCreate(
                ['user_id' => $userId, 'frame_id' => $frameId],
                ['unlocked_at' => now()]
            );
        }
    }

    /**
     * If user has no selected frame, assign level 0 default frame and ensure it is unlocked.
     */
    public function ensureDefaultFrameForUser(User $user): void
    {
        if ($user->selected_frame_id) {
            return;
        }
        $default = Frame::active()
            ->where('level_required', 0)
            ->where('is_premium', false)
            ->first();
        if (!$default) {
            return;
        }
        UserUnlockedFrame::firstOrCreate(
            ['user_id' => $user->id, 'frame_id' => $default->id],
            ['unlocked_at' => now()]
        );
        $user->selected_frame_id = $default->id;
        $user->save();
    }

    /**
     * Build response payload: level, xp, xp_progress_pct, level_up (caller adds if needed).
     */
    public function levelPayload(User $user): array
    {
        $xp = (int) $user->xp;
        $levelRow = $this->levelForXp($xp);
        $levelId = $levelRow ? (int) $levelRow->id : 0;
        $minXp = $levelRow ? (int) $levelRow->min_xp : 0;
        $maxXp = $levelRow ? (int) $levelRow->max_xp : 99;
        $span = max(1, $maxXp - $minXp);
        $progress = $xp - $minXp;
        $xpProgressPct = min(100, round(100 * $progress / $span, 2));

        return [
            'level_up' => false,
            'level' => $levelId,
            'xp' => $xp,
            'xp_progress_pct' => (float) $xpProgressPct,
            'level_min_xp' => $minXp,
            'level_max_xp' => $maxXp,
        ];
    }
}
