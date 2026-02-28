<?php

namespace Database\Seeders;

use App\Models\VirtualGift;
use Illuminate\Database\Seeder;

class VirtualGiftSeeder extends Seeder
{
    /**
     * Seed virtual gifts (wallet send-gift catalog) with rarities.
     */
    public function run(): void
    {
        if (VirtualGift::exists()) {
            return;
        }

        $gifts = [
            ['name' => 'Rose', 'coin_cost' => 10, 'rarity' => 'common'],
            ['name' => 'Heart', 'coin_cost' => 20, 'rarity' => 'common'],
            ['name' => 'Kiss', 'coin_cost' => 50, 'rarity' => 'common'],
            ['name' => 'Diamond', 'coin_cost' => 100, 'rarity' => 'rare'],
            ['name' => 'Flying Carpet', 'coin_cost' => 200, 'rarity' => 'epic'],
            ['name' => 'Crown', 'coin_cost' => 500, 'rarity' => 'epic'],
            ['name' => 'Rocket', 'coin_cost' => 1000, 'rarity' => 'legendary'],
        ];

        foreach ($gifts as $gift) {
            VirtualGift::create(array_merge($gift, ['is_active' => true]));
        }
    }
}
