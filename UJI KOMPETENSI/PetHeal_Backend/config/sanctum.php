<?php

use Laravel\Sanctum\Sanctum;

/**
 * Konfigurasi Sanctum PetHeal.
 *
 * Proyek ini memakai dua jalur autentikasi yang berbeda:
 *  - Android  : Bearer token (`personal_access_tokens`), diverifikasi
 *               middleware `ApiAuthenticate`. Tidak ada cookie, tidak ada SPA.
 *  - Web admin: sesi Blade seperti Laravel biasa.
 *
 * Karena tidak ada SPA, route bawaan `sanctum/csrf-cookie` tidak pernah
 * dipakai klien mana pun. Route itu tetap bisa dipanggil publik, jadi lebih
 * aman dimatikan (`routes => false`) daripada dibiarkan hidup tanpa guna.
 */
return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => ['web'],

    /*
    | Masa berlaku token akses (menit). Token tanpa batas waktu berarti
    | perangkat yang hilang tetap bisa memakai akun selamanya. 30 hari cukup
    | panjang untuk aplikasi klinik, dan aplikasi sudah menangani 401 dengan
    | kembali ke halaman masuk. Set 0 untuk mematikan (perilaku lama).
    */
    'expiration' => (int) env('SANCTUM_EXPIRATION_MINUTES', 43200) ?: null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    | Route `sanctum/csrf-cookie` dimatikan: tidak ada klien cookie/SPA yang
    | membutuhkannya (lihat dokumen di atas).
    */
    'routes' => false,

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
