<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add short unique display_id (6-8 digit) for user-friendly room search.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('display_id', 8)->nullable()->unique()->after('id');
        });

        // Backfill existing rooms with unique 6-digit display_id
        $existing = DB::table('rooms')->whereNull('display_id')->pluck('id');
        $used = DB::table('rooms')->whereNotNull('display_id')->pluck('display_id')->flip()->all();
        foreach ($existing as $id) {
            $displayId = $this->uniqueDisplayId($used);
            $used[$displayId] = true;
            DB::table('rooms')->where('id', $id)->update(['display_id' => $displayId]);
        }
    }

    private function uniqueDisplayId(array &$used): string
    {
        do {
            $id = (string) random_int(100000, 999999);
        } while (isset($used[$id]));
        return $id;
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('display_id');
        });
    }
};
