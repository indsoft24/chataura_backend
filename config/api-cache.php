<?php

return [

    'store' => env('API_CACHE_STORE', 'redis'),
    'redis_connection' => env('API_CACHE_REDIS_CONNECTION', 'cache'),

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
        'feed' => (int) env('API_CACHE_TTL_FEED', 60),
        'reels' => (int) env('API_CACHE_TTL_REELS', 60),
        'profile' => (int) env('API_CACHE_TTL_PROFILE', 600),
        'rooms' => (int) env('API_CACHE_TTL_ROOMS', 30),
        'gifts' => (int) env('API_CACHE_TTL_GIFTS', 86400),
        'detail' => (int) env('API_CACHE_TTL_DETAIL', 300),
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

    'locks' => [
        'seconds' => (int) env('API_CACHE_LOCK_SECONDS', 10),
        'wait_seconds' => (int) env('API_CACHE_LOCK_WAIT_SECONDS', 3),
    ],

    'compression' => [
        'threshold_bytes' => (int) env('API_CACHE_COMPRESS_THRESHOLD_BYTES', 2048),
        'level' => (int) env('API_CACHE_COMPRESS_LEVEL', 6),
    ],

    'limits' => [
        'max_payload_bytes' => (int) env('API_CACHE_MAX_PAYLOAD_BYTES', 1048576),
    ],

    'static_namespaces' => [
        'countries',
        'languages',
        'faq',
        'room_themes',
        'wallet_packages',
        'gift_types',
        'gifts',
        'spin_prizes',
        'music',
    ],

];
