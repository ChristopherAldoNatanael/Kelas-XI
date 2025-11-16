<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    /**
     * Handle an incoming request.
     * Monitor memory usage and request performance
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $startPeakMemory = memory_get_peak_usage();

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $endPeakMemory = memory_get_peak_usage();

        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        $peakMemoryUsed = ($endPeakMemory - $startPeakMemory) / 1024 / 1024; // Convert to MB

        // Log warnings for slow requests or high memory usage
        if ($executionTime > 2000) { // > 2 seconds
            Log::warning('SLOW REQUEST', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time_ms' => round($executionTime, 2),
                'memory_used_mb' => round($memoryUsed, 2),
                'peak_memory_mb' => round($peakMemoryUsed, 2),
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role
            ]);
        }

        if ($memoryUsed > 10) { // > 10MB per request
            Log::warning('HIGH MEMORY USAGE', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'memory_used_mb' => round($memoryUsed, 2),
                'peak_memory_mb' => round($peakMemoryUsed, 2),
                'execution_time_ms' => round($executionTime, 2)
            ]);
        }

        // Add performance headers for debugging
        if (config('app.debug')) {
            $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
            $response->headers->set('X-Memory-Used', round($memoryUsed, 2) . 'MB');
            $response->headers->set('X-Peak-Memory', round($peakMemoryUsed, 2) . 'MB');
        }

        return $response;
    }
}

