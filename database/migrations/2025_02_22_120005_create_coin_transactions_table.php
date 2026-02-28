<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ledger for gift/call coin flows: gross, commission, net.
     */
    public function up(): void
    {
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_type', 20); // GIFT, AUDIO_CALL, VIDEO_CALL
            $table->unsignedBigInteger('reference_id')->nullable(); // e.g. call_session id or virtual_gift id
            $table->integer('gross_coins_deducted');
            $table->integer('admin_commission_coins');
            $table->integer('net_coins_received');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
    }
};
