<?php

namespace Database\Seeders;

use App\Models\AdminSetting;
use Illuminate\Database\Seeder;

class AdminSettingsSeeder extends Seeder
{
    /**
     * Seed admin_settings with sensible defaults.
     */
    public function run(): void
    {
        if (AdminSetting::count() === 0) {
            AdminSetting::create([
                'gift_commission_percent' => 20,
                'audio_call_price_per_min' => 10,
                'audio_call_commission_percent' => 30,
                'video_call_price_per_min' => 20,
                'video_call_commission_percent' => 30,
            ]);
        }
    }
}
