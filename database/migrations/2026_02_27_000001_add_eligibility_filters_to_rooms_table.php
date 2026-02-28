<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Room joining eligibility: host can restrict by gender, country, age.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('allowed_gender', 20)->nullable()->after('settings');
            $table->string('allowed_country', 100)->nullable()->after('allowed_gender');
            $table->unsignedTinyInteger('min_age')->nullable()->after('allowed_country');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['allowed_gender', 'allowed_country', 'min_age', 'max_age']);
        });
    }
};
