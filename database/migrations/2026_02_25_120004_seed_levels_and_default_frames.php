<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed level tiers 0–10 and a default frame (level 0) so profiles can show a frame.
     */
    public function up(): void
    {
        $now = now();
        $levels = [
            ['id' => 0, 'min_xp' => 0, 'max_xp' => 99, 'animation_key' => 'level_0', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 1, 'min_xp' => 100, 'max_xp' => 299, 'animation_key' => 'level_1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'min_xp' => 300, 'max_xp' => 599, 'animation_key' => 'level_2', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'min_xp' => 600, 'max_xp' => 999, 'animation_key' => 'level_3', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'min_xp' => 1000, 'max_xp' => 1999, 'animation_key' => 'level_4', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'min_xp' => 2000, 'max_xp' => 3499, 'animation_key' => 'level_5', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'min_xp' => 3500, 'max_xp' => 5499, 'animation_key' => 'level_6', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'min_xp' => 5500, 'max_xp' => 8499, 'animation_key' => 'level_7', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'min_xp' => 8500, 'max_xp' => 12999, 'animation_key' => 'level_8', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'min_xp' => 13000, 'max_xp' => 19999, 'animation_key' => 'level_9', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'min_xp' => 20000, 'max_xp' => 999999999, 'animation_key' => 'level_10', 'created_at' => $now, 'updated_at' => $now],
        ];
        foreach ($levels as $row) {
            DB::table('levels')->insert($row);
        }

        DB::table('frames')->insert([
            'id' => 1,
            'level_required' => 0,
            'name' => 'Starter',
            'animation_json' => json_encode(['type' => 'none']),
            'is_premium' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('levels')->truncate();
        DB::table('frames')->where('id', 1)->delete();
    }
};
