<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_posts', function (Blueprint $table) {
            $table->unsignedInteger('shares')->default(0)->after('comments');
        });
    }

    public function down(): void
    {
        Schema::table('media_posts', function (Blueprint $table) {
            $table->dropColumn('shares');
        });
    }
};
