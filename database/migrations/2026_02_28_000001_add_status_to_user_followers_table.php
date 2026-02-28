<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Follow requests for private accounts: pending vs accepted.
     */
    public function up(): void
    {
        Schema::table('user_followers', function (Blueprint $table) {
            $table->string('status', 20)->default('accepted')->after('following_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_followers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
