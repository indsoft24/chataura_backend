<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'agora' => [
        'app_id' => env('AGORA_APP_ID'),
        'app_certificate' => env('AGORA_APP_CERTIFICATE'),
        'token_expiry' => env('AGORA_TOKEN_EXPIRY', 3600), // 1 hour in seconds
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', '412016459555-unare7ejevmd28759nn3umseml8kovp2.apps.googleusercontent.com'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'), // Legacy FCM server key for HTTP API
        'v1' => [
            'project_id' => env('FCM_V1_PROJECT_ID'),
            'client_email' => env('FCM_V1_CLIENT_EMAIL'),
            'private_key' => env('FCM_V1_PRIVATE_KEY'), // Raw PEM, use \n for newlines in .env
            'credentials_json_path' => env('FCM_V1_CREDENTIALS_JSON', 'chataura-e67e6-firebase-adminsdk-fbsvc-6eac018db6.json'),
        ],
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
    ],

];
