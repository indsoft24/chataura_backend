<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure room_members has is_active (idempotent).
     */
    public function up(): void
    {
        if (Schema::hasColumn('room_members', 'is_active')) {
            return;
        }

        Schema::table('room_members', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('left_at');
        });

        DB::table('room_members')->whereNull('left_at')->update(['is_active' => true]);
        DB::table('room_members')->whereNotNull('left_at')->update(['is_active' => false]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('room_members', 'is_active')) {
            Schema::table('room_members', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
