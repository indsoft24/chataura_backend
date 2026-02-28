<?php

namespace Database\Seeders;

use App\Models\GiftType;
use Illuminate\Database\Seeder;

class GiftTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gifts = [
            ['name' => 'Rose', 'coin_price' => 10, 'animation_type' => 'fade'],
            ['name' => 'Heart', 'coin_price' => 20, 'animation_type' => 'bounce'],
            ['name' => 'Kiss', 'coin_price' => 50, 'animation_type' => 'pop'],
            ['name' => 'Diamond', 'coin_price' => 100, 'animation_type' => 'sparkle'],
            ['name' => 'Crown', 'coin_price' => 500, 'animation_type' => 'glow'],
            ['name' => 'Rocket', 'coin_price' => 1000, 'animation_type' => 'launch'],
        ];

        foreach ($gifts as $gift) {
            GiftType::create($gift);
        }
    }
}

