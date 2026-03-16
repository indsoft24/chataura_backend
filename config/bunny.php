<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bunny Storage (Bunny CDN)
    |--------------------------------------------------------------------------
    |
    | Storage zone name, API key, and optional region (e.g. "la" for
    | la.storage.bunnycdn.com). Leave region empty for default storage.
    | CDN URL is the pull zone URL for public file access (e.g. https://chataura.b-cdn.net).
    |
    */

    'storage_zone' => env('BUNNY_STORAGE_ZONE', 'chataura'),
    'storage_api_key' => env('BUNNY_STORAGE_API_KEY'),
    'storage_region' => env('BUNNY_STORAGE_REGION', 'la'), // e.g. la, ny, sg; empty = storage.bunnycdn.com
    'cdn_url' => rtrim(env('BUNNY_CDN_URL', 'https://chataura.b-cdn.net'), '/'),

];
