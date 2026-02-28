<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create referral_history for logging referrer/referee rewards.
     */
    public function up(): void
    {
        Schema::create('referral_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referee_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('referrer_amount')->default(0)->comment('Credited to referrer referral_balance');
            $table->unsignedInteger('referee_amount')->default(0)->comment('Credited to referee referral_balance');
            $table->timestamps();

            $table->index(['referrer_id', 'created_at']);
            $table->index(['referee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_history');
    }
};
