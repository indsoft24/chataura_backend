<?php

return [
    'spin_cost' => 10,

    /*
    | Fixed 8 prizes for Lucky Spin. Probability must sum to 1.0.
    | Prize winning total = 0.20 (20%), Loss = 0.80 (80%). Server-side random only.
    */
    'prizes' => [
        ['label' => '50 Coins', 'emoji' => '🪙', 'coins' => 50, 'probability' => 0.05, 'type' => 'coins', 'gift_name' => null],
        ['label' => '100 Coins', 'emoji' => '💰', 'coins' => 100, 'probability' => 0.04, 'type' => 'coins', 'gift_name' => null],
        ['label' => '20 Coins', 'emoji' => '🪙', 'coins' => 20, 'probability' => 0.04, 'type' => 'coins', 'gift_name' => null],
        ['label' => '10 Coins', 'emoji' => '🪙', 'coins' => 10, 'probability' => 0.04, 'type' => 'coins', 'gift_name' => null],
        ['label' => 'Rose', 'emoji' => '🌹', 'coins' => 30, 'probability' => 0.01, 'type' => 'gift', 'gift_name' => 'Rose'],
        ['label' => 'Heart', 'emoji' => '❤️', 'coins' => 50, 'probability' => 0.01, 'type' => 'gift', 'gift_name' => 'Heart'],
        ['label' => '200 Coins', 'emoji' => '💰', 'coins' => 200, 'probability' => 0.01, 'type' => 'coins', 'gift_name' => null],
        ['label' => 'Try Again', 'emoji' => '😢', 'coins' => 0, 'probability' => 0.80, 'type' => 'loss', 'gift_name' => null],
    ],
];
