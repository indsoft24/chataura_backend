<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add wallet_balance (single spendable balance) and total_earned_coins (lifetime earnings).
     * Migrate existing coin_balance into wallet_balance.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('wallet_balance')->default(0)->after('coin_balance');
            $table->integer('total_earned_coins')->default(0)->after('wallet_balance');
        });

        DB::table('users')->update([
            'wallet_balance' => DB::raw('COALESCE(coin_balance, 0)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance', 'total_earned_coins']);
        });
    }
};
