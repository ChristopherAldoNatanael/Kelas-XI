<?php

$midtransIsProduction = filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);

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
        // Toleransi selisih jam (detik) saat verifikasi ID token.
        // Tanpa leeway, token dengan iat "di masa depan" beberapa detik
        // (jam HP > jam server) gagal → login Google 401.
        'token_leeway' => env('FIREBASE_TOKEN_LEEWAY', 60),
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

        // Environment logis untuk snapshot transaksi & gating merchant klinik.
        'environment' => $midtransIsProduction ? 'production' : 'sandbox',

        /*
         * Akun Midtrans Pusat (fallback).
         *
         * Kredensial TIDAK diisi dari database dan tidak dapat diubah oleh
         * Clinic Admin. Nilai lama MIDTRANS_SERVER_KEY / MIDTRANS_CLIENT_KEY
         * tetap dipakai sebagai default supaya instalasi yang sudah berjalan
         * tidak putus.
         */
        'central' => [
            'sandbox' => [
                'merchant_id' => env('MIDTRANS_CENTRAL_SANDBOX_MERCHANT_ID', env('MIDTRANS_MERCHANT_ID')),
                'client_key' => env('MIDTRANS_CENTRAL_SANDBOX_CLIENT_KEY', env('MIDTRANS_CLIENT_KEY')),
                'server_key' => env('MIDTRANS_CENTRAL_SANDBOX_SERVER_KEY', env('MIDTRANS_SERVER_KEY')),
            ],
            'production' => [
                'merchant_id' => env('MIDTRANS_CENTRAL_PRODUCTION_MERCHANT_ID', env('MIDTRANS_MERCHANT_ID')),
                'client_key' => env('MIDTRANS_CENTRAL_PRODUCTION_CLIENT_KEY', env('MIDTRANS_CLIENT_KEY')),
                'server_key' => env('MIDTRANS_CENTRAL_PRODUCTION_SERVER_KEY', env('MIDTRANS_SERVER_KEY')),
            ],
        ],

        /*
         * Fallback ke akun pusat HANYA untuk klinik yang belum pernah
         * mengonfigurasi pembayaran online. Default: aktif di sandbox,
         * nonaktif di production (butuh persetujuan eksplisit).
         */
        'central_fallback' => [
            'enabled' => filter_var(
                env('MIDTRANS_CENTRAL_FALLBACK_ENABLED', !$midtransIsProduction),
                FILTER_VALIDATE_BOOLEAN
            ),
            'production_approved' => filter_var(
                env('MIDTRANS_CENTRAL_FALLBACK_PRODUCTION_APPROVED', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        ],

        /*
         * Midtrans Partner Solutions.
         *
         * Default NONAKTIF. Pembayaran online Pet Shop hanya dibuat bila
         * partner mode aktif DAN merchant klinik berstatus active+verified.
         * Kredensial partner TIDAK PERNAH dikirim ke Android.
         */
        'partner' => [
            'enabled' => filter_var(env('MIDTRANS_PARTNER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'partner_id' => env('MIDTRANS_PARTNER_ID'),
            'server_key' => env('MIDTRANS_PARTNER_SERVER_KEY'),
            'client_key' => env('MIDTRANS_PARTNER_CLIENT_KEY'),
            'platform_merchant_id' => env('MIDTRANS_PARTNER_MERCHANT_ID'),
            'snap_url' => env('MIDTRANS_PARTNER_SNAP_URL') ?: (filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
            'api_url' => env('MIDTRANS_PARTNER_API_URL') ?: (filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)
                ? 'https://api.midtrans.com/v2'
                : 'https://api.sandbox.midtrans.com/v2'),
        ],
    ],

];
