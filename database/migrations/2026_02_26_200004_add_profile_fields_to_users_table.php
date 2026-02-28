<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add profile completion fields: country, bio, gender, dob.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('language');
            $table->string('bio', 150)->nullable()->after('country');
            $table->string('gender', 20)->nullable()->after('bio');
            $table->date('dob')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['country', 'bio', 'gender', 'dob']);
        });
    }
};
