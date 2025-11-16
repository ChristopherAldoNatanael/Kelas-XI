<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CircuitBreakerMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set execution time limit for all requests
        @set_time_limit(30);

        // Circuit breaker logic untuk mencegah overload
        $circuitKey = 'circuit_breaker_' . $request->getClientIp();
        $failureCount = Cache::get($circuitKey . '_failures', 0);

        // Jika terlalu banyak failure dalam 5 menit terakhir, block request
        if ($failureCount >= 10) {
            Log::warning('Circuit breaker activated', [
                'ip' => $request->getClientIp(),
                'failures' => $failureCount,
                'endpoint' => $request->path()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak request. Coba lagi dalam 5 menit.',
                'retry_after' => 300
            ], 429);
        }

        try {
            $response = $next($request);

            // Reset failure count on successful response
            if ($response->getStatusCode() < 500) {
                Cache::forget($circuitKey . '_failures');
            }

            return $response;
        } catch (\Exception $e) {
            // Increment failure count
            $newCount = $failureCount + 1;
            Cache::put($circuitKey . '_failures', $newCount, 300); // 5 minutes

            Log::error('Request failed in circuit breaker', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
                'endpoint' => $request->path(),
                'failures' => $newCount
            ]);

            // Return safe error response
            return response()->json([
                'success' => false,
                'message' => 'Server sedang sibuk. Silakan coba lagi.',
                'data' => []
            ], 503);
        }
    }
}
