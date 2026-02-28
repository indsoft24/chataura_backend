<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Profile frames unlockable by level or premium.
     */
    public function up(): void
    {
        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level_required')->default(0);
            $table->string('name', 100);
            $table->json('animation_json')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->timestamps();

            $table->index('level_required');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frames');
    }
};
