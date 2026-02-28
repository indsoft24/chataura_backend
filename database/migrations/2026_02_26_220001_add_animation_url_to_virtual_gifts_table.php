<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_gifts', function (Blueprint $table) {
            $table->string('animation_url', 500)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_gifts', function (Blueprint $table) {
            $table->dropColumn('animation_url');
        });
    }
};
