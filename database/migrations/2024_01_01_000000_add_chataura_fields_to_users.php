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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('display_name')->nullable()->after('name');
            $table->string('avatar_url')->nullable()->after('display_name');
            $table->integer('level')->default(1)->after('avatar_url');
            $table->integer('exp')->default(0)->after('level');
            $table->integer('coin_balance')->default(0)->after('exp');
            $table->string('fcm_token')->nullable()->after('coin_balance');
            $table->string('invite_code')->unique()->nullable()->after('fcm_token');
            $table->foreignId('invited_by')->nullable()->after('invite_code')->constrained('users')->onDelete('set null');
            $table->index(['phone', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropIndex(['phone', 'email']);
            $table->dropColumn([
                'phone',
                'display_name',
                'avatar_url',
                'level',
                'exp',
                'coin_balance',
                'fcm_token',
                'invite_code',
                'invited_by',
            ]);
        });
    }
};

