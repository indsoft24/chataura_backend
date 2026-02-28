<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'gift_id')) {
                $table->unsignedBigInteger('gift_id')->nullable()->after('message_text');
            }
        });

        Schema::table('call_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('call_logs', 'conversation_id')) {
                $table->foreignId('conversation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('call_logs', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('status');
            }
        });

        Schema::table('user_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('user_devices', 'device_type')) {
                $table->string('device_type', 20)->nullable()->after('fcm_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'gift_id')) {
                $table->dropColumn('gift_id');
            }
        });
        Schema::table('call_logs', function (Blueprint $table) {
            if (Schema::hasColumn('call_logs', 'conversation_id')) {
                $table->dropForeign(['conversation_id']);
                $table->dropColumn('conversation_id');
            }
            if (Schema::hasColumn('call_logs', 'ended_at')) {
                $table->dropColumn('ended_at');
            }
        });
        Schema::table('user_devices', function (Blueprint $table) {
            if (Schema::hasColumn('user_devices', 'device_type')) {
                $table->dropColumn('device_type');
            }
        });
    }
};
