<?php
/**
 * SERVER STABILITY CONFIGURATION
 * Copy paste ke php.ini atau .htaccess
 */

// ==========================================
// PHP.INI OPTIMIZATIONS (512M memory masih crash?)
// ==========================================

return [
    'php_ini_recommendations' => [
        // Memory & Performance
        'memory_limit' => '256M', // Turunkan dari 512M, pakai lebih efisien
        'max_execution_time' => '30',
        'max_input_time' => '60',
        'max_input_vars' => '1000',

        // File handling
        'upload_max_filesize' => '2M',
        'post_max_size' => '8M',
        'max_file_uploads' => '20',

        // Performance
        'realpath_cache_size' => '4096K',
        'realpath_cache_ttl' => '600',
        'opcache.enable' => '1',
        'opcache.memory_consumption' => '128', // Turunkan dari 256
        'opcache.max_accelerated_files' => '4000', // Turunkan dari 7963
        'opcache.revalidate_freq' => '0',
        'opcache.fast_shutdown' => '1',

        // Error handling
        'display_errors' => 'Off',
        'log_errors' => 'On',
        'error_log' => '/path/to/php_error.log',

        // Sessions
        'session.gc_probability' => '1',
        'session.gc_divisor' => '100',
        'session.gc_maxlifetime' => '1440',
    ],

    // ==========================================
    // MY.INI OPTIMIZATIONS (MySQL)
    // ==========================================

    'mysql_optimizations' => [
        '[mysqld]',
        'innodb_buffer_pool_size = 67108864',     // 64MB (turunkan dari 128MB)
        'innodb_log_file_size = 16777216',        // 16MB (turunkan dari 32MB)
        'max_connections = 50',                   // Limit koneksi (turunkan dari 100)
        'query_cache_size = 33554432',            // 32MB (turunkan dari 64MB)
        'query_cache_type = ON',
        'query_cache_limit = 1048576',            // 1MB per query
        'tmp_table_size = 67108864',              // 64MB
        'max_heap_table_size = 67108864',         // 64MB
        'table_open_cache = 2000',
        'thread_cache_size = 50',
        'innodb_flush_log_at_trx_commit = 2',     // Better performance
        'innodb_thread_concurrency = 8',          // Limit threads
        'slow_query_log = 1',
        'slow_query_log_file = C:\xampp\mysql\data\mysql-slow.log',
        'long_query_time = 2',                    // Log queries > 2 seconds
    ],

    // ==========================================
    // LARAVEL SPECIFIC FIXES
    // ==========================================

    'laravel_fixes' => [
        // 1. Fix memory leaks in AppServiceProvider
        'AppServiceProvider_boot' => '
        public function boot()
        {
            // Disable query logging in production
            if (app()->environment("production")) {
                DB::disableQueryLog();
            }

            // Set MySQL strict mode off for better compatibility
            DB::statement("SET SESSION sql_mode = \'\'");

            // Limit database connections
            config(["database.connections.mysql.options" => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
                PDO::ATTR_PERSISTENT => false,
            ]]);
        }',

        // 2. Emergency circuit breaker
        'CircuitBreaker' => '
        // Di routes/api.php, tambahkan global middleware
        Route::middleware(function ($request, $next) {
            static $requestCount = 0;
            $requestCount++;

            // Emergency brake: jika > 100 requests per menit, reject
            $cacheKey = "emergency_brake_" . date("Y-m-d-H-i");
            $currentCount = Cache::increment($cacheKey);
            Cache::put($cacheKey, $currentCount, 60); // 1 minute TTL

            if ($currentCount > 100) {
                return response()->json([
                    "success" => false,
                    "message" => "Server overload. Please try again later."
                ], 503);
            }

            return $next($request);
        })->group(function () {
            // All API routes here
        });',

        // 3. Database connection pooling
        'DatabaseConfig' => '
        // Di config/database.php
        "mysql" => [
            "options" => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ],
            "pool_size" => 10, // Limit connection pool
        ]',
    ],

    // ==========================================
    // QUICK FIXES (Jalankan sekarang!)
    // ==========================================

    'immediate_fixes' => [
        '1_clear_caches' => 'php artisan cache:clear && php artisan config:clear && php artisan route:clear',
        '2_restart_services' => 'Restart Apache/Nginx dan MySQL',
        '3_check_processes' => 'tasklist | findstr "php\|apache\|mysql\|httpd"',
        '4_limit_php_processes' => '
        # Di php-fpm.conf atau pool config:
        pm = static
        pm.max_children = 10  # Limit PHP processes
        pm.max_requests = 500 # Restart worker after 500 requests
        ',
        '5_apache_optimization' => '
        # Di httpd.conf:
        MaxRequestWorkers 50
        ThreadLimit 25
        ThreadsPerChild 25
        MaxConnectionsPerChild 1000
        KeepAlive On
        KeepAliveTimeout 5
        MaxKeepAliveRequests 100
        ',
    ],

    // ==========================================
    // MONITORING QUERIES
    // ==========================================

    'monitoring' => [
        'slow_queries' => '
        // Di AppServiceProvider.php
        DB::listen(function ($query) {
            if ($query->time > 1000) { // > 1 second
                Log::warning("SLOW QUERY: {$query->sql}", [
                    "time" => $query->time,
                    "bindings" => $query->bindings,
                    "url" => request()->fullUrl(),
                    "user" => auth()->user()?->name ?? "guest"
                ]);
            }
        });',

        'memory_usage' => '
        // Di routes/api.php global middleware
        Route::middleware(function ($request, $next) {
            $startMemory = memory_get_usage();
            $response = $next($request);
            $endMemory = memory_get_usage();

            if (($endMemory - $startMemory) > 10485760) { // > 10MB increase
                Log::warning("HIGH MEMORY USAGE", [
                    "url" => $request->fullUrl(),
                    "memory_increase" => round(($endMemory - $startMemory) / 1024 / 1024, 2) . "MB",
                    "peak_memory" => round(memory_get_peak_usage() / 1024 / 1024, 2) . "MB"
                ]);
            }

            return $response;
        })->group(function () {
            // API routes
        });',
    ],
];
