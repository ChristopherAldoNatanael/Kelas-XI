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

    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    | FCM V1 API uses Service Account JSON (not legacy server key)
    |--------------------------------------------------------------------------
    */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'petheal-d8c3d'),
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | FCM (Firebase Cloud Messaging) Configuration
    |--------------------------------------------------------------------------
    | NOTE: This project uses FCM V1 API with Service Account JWT authentication.
    | The 'server_key' below is for LEGACY API only and is NOT USED by FCMService.php.
    | FCM V1 API authenticates via the service account JSON file above.
    |--------------------------------------------------------------------------
    */
    'fcm' => [
        'server_key' => env('FCM_API_KEY'),       // Legacy - not used by current implementation
        'sender_id' => env('FCM_SENDER_ID'),      // Used for Android client-side registration
        'project_id' => env('FIREBASE_PROJECT_ID', 'petheal-d8c3d'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
        'snap_url' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'api_url' => filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2',
    ],

];
