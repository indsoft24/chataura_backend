<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index for fast friend_requests_count: WHERE friend_id = ? AND status = 'pending'.
     */
    public function up(): void
    {
        Schema::table('friendships', function (Blueprint $table) {
            $table->index(['friend_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('friendships', function (Blueprint $table) {
            $table->dropIndex(['friend_id', 'status']);
        });
    }
};
