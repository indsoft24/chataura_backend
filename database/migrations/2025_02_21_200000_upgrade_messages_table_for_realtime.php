<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_type', 20)->default('text')->after('sender_id');
            $table->text('message_text')->nullable()->after('message_type');
            $table->string('message_media', 500)->nullable()->after('message_text');
            $table->string('status', 20)->default('sent')->after('message_media');
        });

        DB::table('messages')->whereNull('message_text')->update([
            'message_text' => DB::raw('COALESCE(message, "")'),
            'message_media' => DB::raw('image_url'),
            'message_type' => DB::raw("CASE WHEN image_url IS NOT NULL AND image_url != '' THEN 'image' ELSE 'text' END"),
        ]);

        Schema::table('messages', function (Blueprint $table) {
            $table->index('conversation_id');
            $table->index('sender_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropIndex(['sender_id']);
            $table->dropIndex(['created_at']);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['message_type', 'message_text', 'message_media', 'status']);
        });
    }
};
