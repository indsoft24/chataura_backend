<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add room lifecycle: host_id, status (active/ended), ended_at, last_activity_at.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('host_id')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active')->after('is_live'); // active, ended
            $table->timestamp('ended_at')->nullable()->after('updated_at');
            $table->timestamp('last_activity_at')->nullable()->after('ended_at');
        });

        \Illuminate\Support\Facades\DB::table('rooms')->whereNull('host_id')->update([
            'host_id' => \Illuminate\Support\Facades\DB::raw('owner_id'),
            'last_activity_at' => \Illuminate\Support\Facades\DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['host_id']);
            $table->dropColumn(['host_id', 'status', 'ended_at', 'last_activity_at']);
        });
    }
};
