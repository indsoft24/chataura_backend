<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | Static/catalog data (countries, languages, FAQ, themes, packages, gifts).
    */
    'ttl' => [
        'static' => (int) env('API_CACHE_TTL_STATIC', 3600),      // 1 hour
        'catalog' => (int) env('API_CACHE_TTL_CATALOG', 1800),    // 30 min
        'spin' => (int) env('API_CACHE_TTL_SPIN', 300),           // 5 min (config-based)
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache key prefixes (global prefix is in config/cache.php)
    |--------------------------------------------------------------------------
    */
    'keys' => [
        'countries' => 'api:countries',
        'languages' => 'api:languages',
        'faq' => 'api:faq',
        'room_themes' => 'api:room_themes',
        'wallet_packages' => 'api:wallet_packages',
        'wallet_gifts' => 'api:wallet_gifts',
        'gift_types' => 'api:gift_types',
        'spin_prizes' => 'api:spin_prizes',
        'profile_frames' => 'api:profile_frames',
        'profile_frames_all' => 'api:profile_frames_all',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags for bulk invalidation (Redis only)
    |--------------------------------------------------------------------------
    */
    'tags' => [
        'static' => 'api:static',
        'catalog' => 'api:catalog',
    ],

];
