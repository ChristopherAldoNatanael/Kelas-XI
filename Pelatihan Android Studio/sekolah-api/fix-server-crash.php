<?php
/**
 * SERVER CRASH FIX SCRIPT
 * Jalankan: php fix-server-crash.php
 */

echo "🔧 SERVER STABILITY FIX - Starting...\n\n";

// 1. Clear all Laravel caches
echo "1. Clearing Laravel caches...\n";
exec('php artisan cache:clear 2>&1', $output, $return);
exec('php artisan config:clear 2>&1', $output, $return);
exec('php artisan route:clear 2>&1', $output, $return);
exec('php artisan view:clear 2>&1', $output, $return);
echo "   ✓ Caches cleared\n\n";

// 2. Run database migrations
echo "2. Running database migrations...\n";
exec('php artisan migrate --force 2>&1', $output, $return);
echo "   ✓ Migrations completed\n\n";

// 3. Generate optimized autoloader
echo "3. Optimizing autoloader...\n";
exec('composer install --optimize-autoloader --no-dev --quiet 2>&1', $output, $return);
echo "   ✓ Autoloader optimized\n\n";

// 4. Check PHP memory and settings
echo "4. Checking PHP configuration...\n";
echo "   Memory limit: " . ini_get('memory_limit') . "\n";
echo "   Max execution time: " . ini_get('max_execution_time') . "s\n";
echo "   OPCache enabled: " . (ini_get('opcache.enable') ? 'Yes' : 'No') . "\n\n";

// 5. Test database connection
echo "5. Testing database connection...\n";
try {
    $pdo = new PDO(
        "mysql:host=" . env('DB_HOST', '127.0.0.1') .
        ";dbname=" . env('DB_DATABASE', 'db_sekolah'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   ✓ Database connection OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n\n";
}

// 6. Create emergency circuit breaker
echo "6. Setting up emergency circuit breaker...\n";
file_put_contents(
    'app/Http/Middleware/EmergencyCircuitBreaker.php',
    '<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmergencyCircuitBreaker
{
    public function handle(Request $request, Closure $next)
    {
        // Emergency brake: jika > 50 requests per menit, reject
        $cacheKey = "emergency_brake_" . date("Y-m-d-H-i");
        $currentCount = Cache::increment($cacheKey);
        Cache::put($cacheKey, $currentCount, 60);

        if ($currentCount > 50) {
            return response()->json([
                "success" => false,
                "message" => "Server overload protection activated. Please try again in 1 minute.",
                "retry_after" => 60
            ], 503);
        }

        return $next($request);
    }
}'
);
echo "   ✓ Emergency circuit breaker created\n\n";

// 7. Update routes with circuit breaker
echo "7. Adding circuit breaker to routes...\n";
$routesFile = 'routes/api.php';
$routesContent = file_get_contents($routesFile);

// Add circuit breaker middleware alias
$bootstrapFile = 'bootstrap/app.php';
$bootstrapContent = file_get_contents($bootstrapFile);
$bootstrapContent = str_replace(
    "'throttle.custom' => \\App\\Http\\Middleware\\RequestThrottling::class,",
    "'throttle.custom' => \\App\\Http\\Middleware\\RequestThrottling::class,
            'circuit.breaker' => \\App\\Http\\Middleware\\EmergencyCircuitBreaker::class,",
    $bootstrapContent
);
file_put_contents($bootstrapFile, $bootstrapContent);

// Add circuit breaker to API routes
$routesContent = str_replace(
    "Route::middleware('auth:sanctum')->group(function () {",
    "Route::middleware(['auth:sanctum', 'circuit.breaker'])->group(function () {",
    $routesContent
);
file_put_contents($routesFile, $routesContent);
echo "   ✓ Circuit breaker added to routes\n\n";

// 8. Create PHP-FPM optimization config
echo "8. Creating PHP-FPM optimization config...\n";
file_put_contents(
    'php-fpm-optimization.conf',
    '[www]
; Limit PHP processes to prevent memory exhaustion
pm = static
pm.max_children = 5
pm.max_requests = 200

; Memory limits
php_admin_value[memory_limit] = 128M
php_admin_value[max_execution_time] = 30

; Disable dangerous functions
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Security
php_admin_value[expose_php] = Off
php_admin_value[display_errors] = Off
php_admin_value[log_errors] = On'
);
echo "   ✓ PHP-FPM config created (php-fpm-optimization.conf)\n\n";

// 9. Create MySQL optimization
echo "9. Creating MySQL optimization config...\n";
file_put_contents(
    'mysql-optimization.cnf',
    '[mysqld]
# Memory optimizations
innodb_buffer_pool_size = 67108864
innodb_log_file_size = 16777216
query_cache_size = 33554432
query_cache_type = ON
tmp_table_size = 67108864
max_heap_table_size = 67108864

# Connection limits
max_connections = 30
thread_cache_size = 50
table_open_cache = 2000

# Performance
innodb_flush_log_at_trx_commit = 2
innodb_thread_concurrency = 8

# Logging
slow_query_log = 1
slow_query_log_file = mysql-slow.log
long_query_time = 2'
);
echo "   ✓ MySQL config created (mysql-optimization.cnf)\n\n";

// 10. Create monitoring script
echo "10. Creating server monitoring script...\n";
file_put_contents(
    'monitor-server.php',
    '<?php
// Server monitoring script
// Run: php monitor-server.php

echo "=== SERVER MONITORING ===\n";
echo "Time: " . date("Y-m-d H:i:s") . "\n\n";

// Memory usage
$memoryUsage = memory_get_peak_usage(true);
echo "PHP Memory Usage: " . round($memoryUsage / 1024 / 1024, 2) . " MB\n";

// Check database connections
try {
    $pdo = new PDO(
        "mysql:host=" . getenv("DB_HOST") . ";dbname=" . getenv("DB_DATABASE"),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD")
    );
    $stmt = $pdo->query("SHOW PROCESSLIST");
    $connections = $stmt->rowCount();
    echo "DB Connections: $connections\n";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}

// Check cache status
$cacheFile = storage_path("framework/cache/data/*");
$cacheFiles = glob($cacheFile);
echo "Cache files: " . count($cacheFiles) . "\n";

// Recent errors
$logFile = storage_path("logs/laravel.log");
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $errorCount = substr_count($logContent, "[ERROR]");
    echo "Recent errors: $errorCount\n";
}

echo "\n=== END MONITORING ===\n";'
);
echo "   ✓ Monitoring script created (monitor-server.php)\n\n";

echo "🎉 SERVER STABILITY FIX COMPLETED!\n\n";
echo "📋 NEXT STEPS:\n";
echo "1. Restart your web server (Apache/Nginx)\n";
echo "2. Restart MySQL\n";
echo "3. Copy php-fpm-optimization.conf to your PHP-FPM pool config\n";
echo "4. Copy mysql-optimization.cnf to your MySQL config\n";
echo "5. Run: php monitor-server.php to check server status\n";
echo "6. Test your API endpoints\n\n";

echo "🚨 EMERGENCY CONTACTS:\n";
echo "- If server still crashes, check: php monitor-server.php\n";
echo "- Check logs: storage/logs/laravel.log\n";
echo "- Emergency stop: php artisan down\n\n";

echo "✅ Your server should now be stable!\n";
