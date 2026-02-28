<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure users table has fcm_token for push notifications (WhatsApp-level delivery).
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'fcm_token')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->text('fcm_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'fcm_token')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
