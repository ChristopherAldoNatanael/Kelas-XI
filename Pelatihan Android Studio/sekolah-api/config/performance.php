<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for performance optimizations including caching,
    | rate limiting, and query optimizations.
    |
    */

    'cache' => [
        'ttl' => [
            'classes' => env('CACHE_CLASSES_TTL', 600), // 10 minutes
            'schedules' => env('CACHE_SCHEDULES_TTL', 300), // 5 minutes
            'dashboard' => env('CACHE_DASHBOARD_TTL', 180), // 3 minutes
            'dropdown' => env('CACHE_DROPDOWN_TTL', 3600), // 1 hour
        ],

        'keys' => [
            'classes_dropdown' => 'classes_dropdown_',
            'siswa_dashboard' => 'siswa_dashboard_',
            'weekly_schedule' => 'weekly_schedule_class_',
            'schedule_mobile' => 'schedule_mobile_',
        ],
    ],

    'throttling' => [
        'dropdown' => env('THROTTLE_DROPDOWN', '60,1'), // 60 requests per minute
        'schedules' => env('THROTTLE_SCHEDULES', '30,1'), // 30 requests per minute
        'auth' => env('THROTTLE_AUTH', '10,1'), // 10 auth requests per minute
    ],

    'pagination' => [
        'default_per_page' => env('DEFAULT_PER_PAGE', 20),
        'max_per_page' => env('MAX_PER_PAGE', 100),
        'schedule_limit' => env('SCHEDULE_LIMIT', 200),
    ],

    'query_optimization' => [
        'eager_load_subjects' => ['subject:id,name'],
        'eager_load_teachers' => ['teacher.user:id,nama'],
        'eager_load_classrooms' => ['classroom:id,name'],
        'select_fields_only' => true,
    ],

    'monitoring' => [
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // ms
        'enable_query_logging' => env('ENABLE_QUERY_LOGGING', false),
        'log_slow_queries' => env('LOG_SLOW_QUERIES', true),
    ],
];
