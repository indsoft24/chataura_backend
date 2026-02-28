<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create countries table for dynamic country list with flags.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name');
            $table->string('flag_url', 500)->nullable();
            $table->string('flag_emoji', 10)->nullable();
            $table->timestamps();
        });

        DB::table('countries')->insert([
            ['id' => 'IN', 'name' => 'India', 'flag_url' => null, 'flag_emoji' => '🇮🇳', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'BD', 'name' => 'Bangladesh', 'flag_url' => null, 'flag_emoji' => '🇧🇩', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'PK', 'name' => 'Pakistan', 'flag_url' => null, 'flag_emoji' => '🇵🇰', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'US', 'name' => 'United States', 'flag_url' => null, 'flag_emoji' => '🇺🇸', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'GB', 'name' => 'United Kingdom', 'flag_url' => null, 'flag_emoji' => '🇬🇧', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
