<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for feed queries and engagement sorting.
     */
    public function up(): void
    {
        Schema::table('media_posts', function (Blueprint $table) {
            $table->index('type');
            $table->index('created_at');
            $table->index(['likes', 'comments']);
        });
    }

    public function down(): void
    {
        Schema::table('media_posts', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['likes', 'comments']);
        });
    }
};
