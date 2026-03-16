<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_posts', function (Blueprint $table) {
            $table->string('music_url', 500)->nullable()->after('thumbnail_url');
            $table->string('effect_name', 100)->nullable()->after('music_url');
            $table->integer('duration')->nullable()->after('effect_name');
            $table->string('aspect_ratio', 20)->nullable()->after('duration');
            $table->boolean('is_camera_recorded')->default(false)->after('aspect_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('media_posts', function (Blueprint $table) {
            $table->dropColumn([
                'music_url',
                'effect_name',
                'duration',
                'aspect_ratio',
                'is_camera_recorded',
            ]);
        });
    }
};

