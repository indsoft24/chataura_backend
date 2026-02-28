<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_active; migrate role to host/co_host/speaker/listener.
     */
    public function up(): void
    {
        Schema::table('room_members', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('left_at');
        });

        DB::table('room_members')->whereNull('left_at')->update(['is_active' => true]);
        DB::table('room_members')->whereNotNull('left_at')->update(['is_active' => false]);

        // Change role column to string for host/co_host/speaker/listener (MySQL enum -> varchar)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE room_members MODIFY role VARCHAR(20) NOT NULL DEFAULT 'listener'");
        } else {
            Schema::table('room_members', function (Blueprint $table) {
                $table->string('role', 20)->default('listener')->change();
            });
        }

        // Backfill: owner -> host, host -> co_host, member -> speaker (if seat) else listener
        $members = DB::table('room_members')->get();
        foreach ($members as $m) {
            $newRole = match ($m->role) {
                'owner' => 'host',
                'host' => 'co_host',
                'member' => ($m->seat_index !== null ? 'speaker' : 'listener'),
                default => in_array($m->role, ['host', 'co_host', 'speaker', 'listener'], true) ? $m->role : 'listener',
            };
            DB::table('room_members')->where('id', $m->id)->update(['role' => $newRole]);
        }
    }

    public function down(): void
    {
        Schema::table('room_members', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::table('room_members', function (Blueprint $table) {
            $table->enum('role', ['owner', 'host', 'member'])->default('member')->change();
        });
    }
};
