<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Frame;
use App\Services\LevelService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileLevelController extends Controller
{
    public function __construct(
        private LevelService $levelService
    ) {}

    /**
     * POST /xp/add - Add XP to the authenticated user. Recalculates level and unlocks frames.
     * Body: amount (integer, min 1, max 10000 per request to prevent abuse).
     */
    public function addXp(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|integer|min:1|max:10000',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $payload = $this->levelService->addXp($user->id, (int) $validated['amount']);

        return ApiResponse::success($this->levelPayloadForApp($payload));
    }

    /**
     * GET /profile/details - Level, xp, selected_frame, unlocked_frames, available_frames.
     * If user has no selected frame, assigns level 0 default frame.
     */
    public function details(Request $request)
    {
        $user = $request->user();
        $levelRow = $this->levelService->levelForXp((int) $user->xp);
        $currentLevelId = $levelRow ? (int) $levelRow->id : 0;
        if ((int) $user->level !== $currentLevelId) {
            $user->level = $currentLevelId;
            $user->save();
        }
        $this->levelService->unlockFramesForLevel($user->id, $currentLevelId);
        $this->levelService->ensureDefaultFrameForUser($user);

        $user->refresh()->load(['unlockedFrames', 'selectedFrame']);

        $levelPayload = $this->levelService->levelPayload($user);
        $levelPayload['animation_key'] = $levelRow?->animation_key ?? null;

        $unlockedFrames = $user->unlockedFrames->map(fn (Frame $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'level_required' => $f->level_required,
            'animation_key' => $f->animation_key,
            'animation_json' => $f->animation_json,
            'is_active' => $f->is_active,
            'unlocked_at' => $f->pivot?->unlocked_at?->toIso8601String(),
        ])->values()->all();

        $selectedFrame = $user->selectedFrame ? [
            'id' => $user->selectedFrame->id,
            'name' => $user->selectedFrame->name,
            'level_required' => $user->selectedFrame->level_required,
            'animation_key' => $user->selectedFrame->animation_key,
            'animation_json' => $user->selectedFrame->animation_json,
            'is_active' => $user->selectedFrame->is_active,
        ] : null;

        $allFrames = Frame::active()->orderBy('level_required')->orderBy('id')->get();
        $unlockedIds = $user->unlockedFrames->pluck('id')->all();
        $selectedFrameId = (int) $user->selected_frame_id;
        $availableFrames = $allFrames->map(fn (Frame $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'level_required' => $f->level_required,
            'animation_key' => $f->animation_key,
            'animation_json' => $f->animation_json,
            'is_active' => $f->is_active,
            'unlocked' => in_array($f->id, $unlockedIds, true),
            'selected' => $f->id === $selectedFrameId,
        ])->values()->all();

        return ApiResponse::success([
            'level' => $levelPayload['level'],
            'xp' => $levelPayload['xp'],
            'xp_progress_pct' => $levelPayload['xp_progress_pct'],
            'level_min_xp' => $levelPayload['level_min_xp'],
            'level_max_xp' => $levelPayload['level_max_xp'],
            'animation_key' => $levelPayload['animation_key'],
            'selected_frame' => $selectedFrame,
            'unlocked_frames' => $unlockedFrames,
            'available_frames' => $availableFrames,
        ]);
    }

    /**
     * GET /profile/level or GET /user/level - Current user's level and XP progress.
     * Returns level, xp, levelMaxXp, xpProgressPct, levelUp for app compatibility.
     */
    public function level(Request $request)
    {
        $user = $request->user();
        $levelRow = $this->levelService->levelForXp((int) $user->xp);
        $currentLevelId = $levelRow ? (int) $levelRow->id : 0;
        if ((int) $user->level !== $currentLevelId) {
            $user->level = $currentLevelId;
            $user->save();
        }
        $this->levelService->unlockFramesForLevel($user->id, $currentLevelId);

        $payload = $this->levelService->levelPayload($user);
        $payload['animation_key'] = $levelRow?->animation_key;

        return ApiResponse::success($this->levelPayloadForApp($payload));
    }

    /**
     * GET /profile/frames/all - All frames for grid: full list with locked/unlocked and selected flags.
     * Client can merge this with buildFrameModels to show locked placeholders + unlocked + selected.
     */
    public function allFrames(Request $request)
    {
        $user = $request->user();
        $user->load(['unlockedFrames' => fn ($q) => $q->orderBy('level_required')]);
        $unlockedIds = $user->unlockedFrames->pluck('id')->all();
        $unlockedAt = $user->unlockedFrames->keyBy('id')->map(function ($f) {
            $at = $f->pivot?->unlocked_at;
            if ($at === null) {
                return null;
            }
            return $at instanceof Carbon ? $at->toIso8601String() : Carbon::parse($at)->toIso8601String();
        })->all();
        $selectedFrameId = (int) $user->selected_frame_id;

        $frames = Frame::active()->orderBy('level_required')->orderBy('id')->get();

        $list = $frames->map(fn (Frame $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'level_required' => $f->level_required,
            'animation_json' => $f->animation_json,
            'is_premium' => $f->is_premium,
            'unlocked' => in_array($f->id, $unlockedIds, true),
            'unlocked_at' => $unlockedAt[$f->id] ?? null,
            'selected' => $f->id === $selectedFrameId,
        ]);

        return ApiResponse::success([
            'frames' => $list,
            'selected_frame_id' => $selectedFrameId ?: null,
        ]);
    }

    /**
     * GET /profile/frames - Unlocked frames and currently selected frame.
     */
    public function frames(Request $request)
    {
        $user = $request->user();
        $levelRow = $this->levelService->levelForXp((int) $user->xp);
        $currentLevelId = $levelRow ? (int) $levelRow->id : 0;
        $this->levelService->unlockFramesForLevel($user->id, $currentLevelId);
        $this->levelService->ensureDefaultFrameForUser($user);

        $user->refresh()->load(['unlockedFrames', 'selectedFrame']);

        $unlocked = $user->unlockedFrames->map(fn (Frame $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'level_required' => $f->level_required,
            'animation_json' => $f->animation_json,
            'is_premium' => $f->is_premium,
            'unlocked_at' => $f->pivot?->unlocked_at?->toIso8601String(),
        ]);

        $selected = $user->selectedFrame ? [
            'id' => $user->selectedFrame->id,
            'name' => $user->selectedFrame->name,
            'animation_json' => $user->selectedFrame->animation_json,
        ] : null;

        return ApiResponse::success([
            'unlocked_frames' => $unlocked,
            'selected_frame' => $selected,
        ]);
    }

    /**
     * POST /profile/select-frame - Set the user's selected profile frame. Validates user owns (unlocked) the frame.
     */
    public function selectFrame(Request $request)
    {
        try {
            $validated = $request->validate([
                'frame_id' => 'required|integer|exists:frames,id',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $frameId = (int) $validated['frame_id'];

        $frame = Frame::active()->find($frameId);
        if (!$frame) {
            return ApiResponse::error('INVALID_FRAME', 'Frame is not available', 400);
        }

        $unlocked = $user->unlockedFrames()->where('frames.id', $frameId)->exists();
        if (!$unlocked) {
            return ApiResponse::forbidden('You have not unlocked this frame');
        }

        $user->selected_frame_id = $frameId;
        $user->save();

        return ApiResponse::success([
            'selected_frame' => [
                'id' => $frame->id,
                'name' => $frame->name,
                'level_required' => $frame->level_required,
                'animation_key' => $frame->animation_key,
                'animation_json' => $frame->animation_json,
                'is_active' => $frame->is_active,
            ],
        ]);
    }

    /**
     * Map level payload to app shape: level, xp, levelMaxXp, xpProgressPct, levelUp.
     */
    private function levelPayloadForApp(array $payload): array
    {
        return [
            'level' => (int) ($payload['level'] ?? 0),
            'xp' => (int) ($payload['xp'] ?? 0),
            // exp kept as alias for xp so older analytics that read `exp`
            // continue to work while xp is the source of truth.
            'exp' => (int) ($payload['xp'] ?? 0),
            'levelMaxXp' => (int) ($payload['level_max_xp'] ?? 0),
            'xpProgressPct' => (float) ($payload['xp_progress_pct'] ?? 0),
            'levelUp' => (bool) ($payload['level_up'] ?? false),
            'level_min_xp' => (int) ($payload['level_min_xp'] ?? 0),
            'level_max_xp' => (int) ($payload['level_max_xp'] ?? 0),
            'xp_progress_pct' => (float) ($payload['xp_progress_pct'] ?? 0),
            'animation_key' => $payload['animation_key'] ?? null,
        ];
    }
}
