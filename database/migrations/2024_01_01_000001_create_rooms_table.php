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
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('agora_channel_name')->unique();
            $table->integer('agora_channel_uid')->nullable();
            $table->integer('max_seats')->default(8);
            $table->boolean('is_live')->default(true);
            $table->string('cover_image_url')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('settings')->nullable(); // allow_video, allow_gifts, allow_games
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_live', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

