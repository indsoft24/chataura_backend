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
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('gift_commission_percent')->default(20);
            $table->integer('audio_call_price_per_min')->default(10);
            $table->integer('audio_call_commission_percent')->default(30);
            $table->integer('video_call_price_per_min')->default(20);
            $table->integer('video_call_commission_percent')->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
