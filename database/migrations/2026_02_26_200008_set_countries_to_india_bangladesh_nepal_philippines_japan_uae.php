<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set countries list to: India, Bangladesh, Nepal, Philippines, Japan, UAE.
     */
    public function up(): void
    {
        DB::table('countries')->delete();

        DB::table('countries')->insert([
            ['id' => 'IN', 'name' => 'India', 'flag_url' => null, 'flag_emoji' => '🇮🇳', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'BD', 'name' => 'Bangladesh', 'flag_url' => null, 'flag_emoji' => '🇧🇩', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'NP', 'name' => 'Nepal', 'flag_url' => null, 'flag_emoji' => '🇳🇵', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'PH', 'name' => 'Philippines', 'flag_url' => null, 'flag_emoji' => '🇵🇭', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'JP', 'name' => 'Japan', 'flag_url' => null, 'flag_emoji' => '🇯🇵', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'AE', 'name' => 'UAE', 'flag_url' => null, 'flag_emoji' => '🇦🇪', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('countries')->delete();
        DB::table('countries')->insert([
            ['id' => 'IN', 'name' => 'India', 'flag_url' => null, 'flag_emoji' => '🇮🇳', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'BD', 'name' => 'Bangladesh', 'flag_url' => null, 'flag_emoji' => '🇧🇩', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'PK', 'name' => 'Pakistan', 'flag_url' => null, 'flag_emoji' => '🇵🇰', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'US', 'name' => 'United States', 'flag_url' => null, 'flag_emoji' => '🇺🇸', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'GB', 'name' => 'United Kingdom', 'flag_url' => null, 'flag_emoji' => '🇬🇧', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
