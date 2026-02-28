<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add animation_key and is_active to frames for profile frame system.
     */
    public function up(): void
    {
        Schema::table('frames', function (Blueprint $table) {
            $table->string('animation_key', 100)->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('is_premium');
        });
    }

    public function down(): void
    {
        Schema::table('frames', function (Blueprint $table) {
            $table->dropColumn(['animation_key', 'is_active']);
        });
    }
};
