<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wealth_privileges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->string('icon_identifier', 50);
            $table->unsignedTinyInteger('level_required')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('wealth_privileges')->insert([
            ['title' => 'Entry Bar', 'description' => 'There will be a striking bar when you enter a room', 'icon_identifier' => 'entry_bar', 'level_required' => 0, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Nameplate', 'description' => 'Show your noble status', 'icon_identifier' => 'nameplate', 'level_required' => 2, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Screen Share', 'description' => 'Share your screen with the room', 'icon_identifier' => 'screen_share', 'level_required' => 5, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wealth_privileges');
    }
};
