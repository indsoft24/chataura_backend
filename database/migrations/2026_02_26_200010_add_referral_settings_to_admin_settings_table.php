<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add referral reward and conversion rate to admin_settings.
     */
    public function up(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->unsignedInteger('referral_reward_referrer')->default(100)->after('video_call_commission_percent');
            $table->unsignedInteger('referral_reward_referee')->default(50)->after('referral_reward_referrer');
            $table->unsignedInteger('referral_coin_conversion_rate')->default(1)->after('referral_reward_referee')->comment('Gold coins per 1 referral coin');
        });

    }

    public function down(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->dropColumn(['referral_reward_referrer', 'referral_reward_referee', 'referral_coin_conversion_rate']);
        });
    }
};
