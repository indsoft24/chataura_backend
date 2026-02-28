<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Professional 1-1 call lifecycle (ringing → accepted/rejected/ended/missed).
     */
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel_name', 255);
            $table->text('agora_token')->nullable();
            $table->string('call_type', 20)->default('audio'); // audio, video
            $table->string('status', 20)->default('ringing'); // ringing, accepted, rejected, ended, missed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('caller_id');
            $table->index('receiver_id');
            $table->index('status');
            $table->index(['receiver_id', 'status']);
            $table->index(['caller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
