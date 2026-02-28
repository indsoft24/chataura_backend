<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // User profile & settings fields
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->after('coin_balance');
            $table->unsignedInteger('gems')->default(0)->after('language');
            $table->boolean('private_account')->default(false)->after('gems');
            $table->boolean('show_online_status')->default(true)->after('private_account');
            $table->boolean('message_notifications')->default(true)->after('show_online_status');
            $table->boolean('room_notifications')->default(true)->after('message_notifications');
            $table->boolean('gift_notifications')->default(true)->after('room_notifications');
        });

        // Chat groups first (no FK to conversations yet)
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->string('type', 50)->default('general');
            $table->boolean('is_private')->default(true);
            $table->boolean('allow_visitors')->default(false);
            $table->boolean('auto_approve')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->timestamps();
        });

        // Conversations (group_id -> chat_groups)
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('private');
            $table->string('name')->nullable();
            $table->string('image_url')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('chat_groups')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('chat_groups', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'friend_id']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained('chat_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamps();
            $table->unique(['chat_group_id', 'user_id']);
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->string('screenshot_url')->nullable();
            $table->timestamps();
        });

        Schema::create('blocked_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'language', 'gems', 'private_account', 'show_online_status',
                'message_notifications', 'room_notifications', 'gift_notifications',
            ]);
        });
        Schema::dropIfExists('blocked_users');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('friendships');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::table('chat_groups', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('chat_groups');
    }
};
