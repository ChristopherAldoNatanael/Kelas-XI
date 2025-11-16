<?php

// Database optimization configuration
return [
    // Query optimization settings
    'query_optimization' => [
        'enable_eager_loading' => true,
        'max_results_per_page' => 50,
        'cache_duration' => 600, // 10 minutes (reduced from 1 hour)
        'enable_query_cache' => true,
    ],

    // Database connection optimization
    'connection' => [
        'pooling' => true,
        'max_connections' => 10,
        'connection_timeout' => 30,
        'query_timeout' => 10,
    ],

    // Model-specific optimization
    'models' => [
        'schedule' => [
            'default_relations' => [
                'class:id,name',
                'subject:id,name,code',
                'teacher:id,user_id,teacher_code',
                'teacher.user:id,nama',
                'classroom:id,name,code',
            ],
            'search_fields' => [
                'subject.name',
                'subject.code',
                'teacher.user.nama',
                'class.name',
            ],
            'indexed_fields' => [
                'class_id',
                'subject_id',
                'teacher_id',
                'day_of_week',
                'period_number',
                'status',
                'academic_year',
                'semester'
            ],
        ],
    ],

    // Cache settings
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'tags' => [
            'schedules',
            'academic',
            'timetable',
        ],
    ],

    // Performance settings
    'performance' => [
        'enable_pagination' => true,
        'default_per_page' => 15, // Reduced from 25 to prevent memory issues
        'max_per_page' => 50, // Reduced from 100 to prevent memory issues
        'enable_search' => true,
        'enable_filters' => true,
    ],
];
