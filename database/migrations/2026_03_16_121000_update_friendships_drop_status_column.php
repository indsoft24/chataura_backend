<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: we keep the column for compatibility but no longer use it in code.
    }

    public function down(): void
    {
        // No-op
    }
};

