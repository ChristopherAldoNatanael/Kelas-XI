<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // CRITICAL FIX: Prevent memory leaks and server crashes

        // 1. Set PHP memory limit if not already set
        $currentMemoryLimit = ini_get('memory_limit');
        if ($currentMemoryLimit && $this->returnBytes($currentMemoryLimit) < 256 * 1024 * 1024) {
            @ini_set('memory_limit', '256M');
        }

        // 2. Disable query logging in production to save memory
        if (!config('app.debug')) {
            DB::disableQueryLog();
        }

        // 3. Set MySQL to non-strict mode for better compatibility
        try {
            DB::statement("SET SESSION sql_mode = ''");
        } catch (\Exception $e) {
            Log::warning('Could not set SQL mode: ' . $e->getMessage());
        }

        // 4. Listen for database connection issues and log them
        DB::listen(function ($query) {
            // Only log slow queries (> 1 second) to prevent log bloat
            if ($query->time > 1000) {
                Log::warning('Slow query detected', [
                    'sql' => substr($query->sql, 0, 200), // Truncate long queries
                    'time' => $query->time . 'ms',
                    'bindings_count' => count($query->bindings)
                ]);
            }

            // Critical: Log queries that might cause memory issues
            if (stripos($query->sql, 'SELECT') !== false && stripos($query->sql, 'LIMIT') === false) {
                // Check if query might return too many rows
                if (stripos($query->sql, 'count(*)') === false) {
                    Log::warning('Query without LIMIT detected', [
                        'sql' => substr($query->sql, 0, 200)
                    ]);
                }
            }
        });

        // 5. Handle database connection failures gracefully
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            Log::error('Database connection failed: ' . $e->getMessage());
            // Don't throw exception - let the app continue and fail gracefully per request
        }

        // 6. Cache fallback: If Redis fails, switch to file automatically
        try {
            $cacheDriver = config('cache.default');
            if ($cacheDriver === 'redis') {
                Cache::get('test_connection');
            }
        } catch (\Exception $e) {
            Log::warning('Cache driver (Redis) not available, falling back to file cache');
            config(['cache.default' => 'file']);
        }
    }

    /**
     * Convert memory limit string to bytes
     */
    private function returnBytes($val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
                // fall through
            case 'm':
                $val *= 1024;
                // fall through
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
