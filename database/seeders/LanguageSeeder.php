<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'sort_order' => 1],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'sort_order' => 2],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'sort_order' => 3],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'sort_order' => 4],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'sort_order' => 5],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'sort_order' => 6],
        ];
        foreach ($items as $item) {
            Language::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
