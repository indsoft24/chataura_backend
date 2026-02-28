<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->string('full_name', 255)->nullable()->after('ifsc_code');
            $table->string('bank_name', 255)->nullable()->after('full_name');
            $table->string('bank_address', 500)->nullable()->after('bank_name');
            $table->string('swift_code', 50)->nullable()->after('bank_address');
            $table->string('country', 100)->nullable()->after('swift_code');
            $table->boolean('is_international')->default(false)->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'bank_name',
                'bank_address',
                'swift_code',
                'country',
                'is_international',
            ]);
        });
    }
};

