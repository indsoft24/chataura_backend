<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_gifts', function (Blueprint $table) {
            $table->string('rarity', 20)->default('common')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_gifts', function (Blueprint $table) {
            $table->dropColumn('rarity');
        });
    }
};
