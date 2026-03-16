<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Room auto-termination: heartbeat timestamps for seats and host.
     * Cron frees seats / ends rooms when heartbeat is older than 60 seconds.
     */
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('is_locked');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->timestamp('host_last_heartbeat_at')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('host_last_heartbeat_at');
        });
    }
};
