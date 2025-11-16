<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Android App Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi khusus untuk optimasi aplikasi Android siswa
    |
    */

    // Timeout settings untuk mencegah server crash
    'timeout' => [
        'max_execution_time' => 30,
        'lightweight_timeout' => 10,
        'critical_timeout' => 5,
    ],

    // Cache settings
    'cache' => [
        'jadwal_duration' => 300, // 5 minutes
        'kehadiran_duration' => 180, // 3 minutes
        'today_schedule_duration' => 120, // 2 minutes
        'user_schedule_duration' => 600, // 10 minutes
    ],

    // Pagination limits untuk mencegah overload
    'pagination' => [
        'max_per_page' => 50,
        'default_per_page' => 20,
        'siswa_max_per_page' => 20,
        'siswa_default_per_page' => 10,
    ],

    // Circuit breaker settings
    'circuit_breaker' => [
        'failure_threshold' => 10,
        'recovery_timeout' => 300, // 5 minutes
        'half_open_max_calls' => 3,
    ],

    // Rate limiting untuk endpoint siswa
    'rate_limits' => [
        'siswa_general' => '60,1', // 60 requests per minute
        'siswa_kehadiran' => '30,1', // 30 requests per minute
        'siswa_lightweight' => '100,1', // 100 requests per minute untuk endpoint ringan
    ],

    // Database query limits
    'query_limits' => [
        'max_schedule_items' => 100,
        'max_attendance_items' => 50,
        'daily_schedule_limit' => 20,
        'weekly_schedule_limit' => 100,
    ],

    // Monitoring settings
    'monitoring' => [
        'slow_query_threshold' => 1000, // ms
        'memory_limit_warning' => '128M',
        'log_performance' => env('LOG_PERFORMANCE', true),
    ],

    // Emergency mode settings
    'emergency' => [
        'enable_emergency_mode' => env('EMERGENCY_MODE', false),
        'emergency_response_only' => false,
        'maintenance_message' => 'Server sedang dalam maintenance. Silakan coba beberapa menit lagi.',
    ],
];
