<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Heartbeat for ghost-call prevention: stale accepted calls can be auto-terminated.
     */
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('ended_at');
        });
        Schema::table('calls', function (Blueprint $table) {
            $table->index(['status', 'last_heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_heartbeat_at']);
            $table->dropColumn('last_heartbeat_at');
        });
    }
};
