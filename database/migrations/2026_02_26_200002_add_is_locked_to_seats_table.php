<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_locked to seats for host-controlled locking.
     */
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_muted');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
