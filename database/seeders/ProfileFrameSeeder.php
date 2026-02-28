<?php

namespace Database\Seeders;

use App\Models\Frame;
use Illuminate\Database\Seeder;

/**
 * Seeds profile frames for each level (0-10). Each frame has id, name, level_required, animation_key, is_active = true.
 */
class ProfileFrameSeeder extends Seeder
{
    protected array $frameNames = [
        0 => 'Starter',
        1 => 'Bronze',
        2 => 'Silver',
        3 => 'Gold',
        4 => 'Platinum',
        5 => 'Diamond',
        6 => 'Master',
        7 => 'Grandmaster',
        8 => 'Champion',
        9 => 'Legend',
        10 => 'Elite',
    ];

    public function run(): void
    {
        foreach (range(0, 10) as $level) {
            $frame = Frame::where('level_required', $level)->first();
            $attrs = [
                'name' => $this->frameNames[$level] ?? 'Level ' . $level,
                'animation_key' => 'frame_' . $level,
                'animation_json' => ['key' => 'frame_' . $level],
                'is_premium' => false,
                'is_active' => true,
            ];
            if ($frame) {
                $frame->update($attrs);
            } else {
                Frame::create(array_merge(['level_required' => $level], $attrs));
            }
        }
    }
}
