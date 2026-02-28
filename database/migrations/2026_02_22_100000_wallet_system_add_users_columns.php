<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addWalletBalance = !Schema::hasColumn('users', 'wallet_balance');
        $addTotalEarned = !Schema::hasColumn('users', 'total_earned_coins');

        Schema::table('users', function (Blueprint $table) use ($addWalletBalance, $addTotalEarned) {
            if ($addWalletBalance) {
                $table->integer('wallet_balance')->default(0)->after('coin_balance');
            }
            if ($addTotalEarned) {
                $table->integer('total_earned_coins')->default(0)->after('wallet_balance');
            }
        });

        if ($addWalletBalance && Schema::hasColumn('users', 'coin_balance')) {
            \DB::table('users')->update(['wallet_balance' => \DB::raw('COALESCE(coin_balance, 0)')]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(['wallet_balance', 'total_earned_coins'], fn ($c) => Schema::hasColumn('users', $c));
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
