<?php

return [
    'routing_provider' => env('ROUTING_PROVIDER', 'demo'),
    'google' => [
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
    ],
    'sms' => [
        'enabled' => env('SMS_FEATURE_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'fake'),
        'message' => env('SMS_MESSAGE', 'Το όχημά μας πλησιάζει στη διεύθυνσή σας.'),
    ],
    'modulus' => [
        'base_url' => env('MODULUS_BASE_URL', 'https://messaging.modulus.gr'),
        'access_key' => env('MODULUS_ACCESS_KEY'),
        'secret_key' => env('MODULUS_SECRET_KEY'),
        'sender_id' => env('MODULUS_SENDER_ID'),
        'routing' => env('MODULUS_ROUTING'),
        'dlr_token' => env('MODULUS_DLR_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
