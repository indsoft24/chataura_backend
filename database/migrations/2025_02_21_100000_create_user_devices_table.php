<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token', 500);
            $table->string('platform', 20)->default('android'); // android, ios, web
            $table->timestamps();
            $table->index('user_id');
            $table->index('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
