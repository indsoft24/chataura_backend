<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('artist', 200)->nullable();
            $table->string('file_url', 500);
            $table->timestamps();

            $table->unique('file_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_tracks');
    }
};

