<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Add xp (gamification) and selected_frame_id for profile frame.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('xp')->default(0)->after('exp');
            $table->foreignId('selected_frame_id')->nullable()->after('xp')->constrained('frames')->nullOnDelete();
        });

        // Sync existing exp to xp so xp is the source of truth for gamification
        \Illuminate\Support\Facades\DB::table('users')->update([
            'xp' => \Illuminate\Support\Facades\DB::raw('COALESCE(exp, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['selected_frame_id']);
            $table->dropColumn(['xp', 'selected_frame_id']);
        });
    }
};
