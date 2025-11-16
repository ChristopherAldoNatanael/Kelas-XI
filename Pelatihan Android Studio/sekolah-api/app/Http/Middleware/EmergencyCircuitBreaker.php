<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmergencyCircuitBreaker
{
    public function handle(Request $request, Closure $next)
    {
        // CRITICAL FIX: Increased limit from 50 to 200 requests per minute
        // Previous limit was too aggressive and blocked legitimate student traffic
        // Use per-IP tracking to prevent abuse while allowing normal usage

        $ip = $request->ip();
        $cacheKey = "circuit_breaker_{$ip}_" . date("Y-m-d-H-i");

        try {
            $currentCount = Cache::get($cacheKey, 0);
            $currentCount++;
            Cache::put($cacheKey, $currentCount, 60); // 1 minute TTL

            // Allow up to 200 requests per minute per IP
            if ($currentCount > 200) {
                Log::warning('Circuit breaker triggered', [
                    'ip' => $ip,
                    'count' => $currentCount,
                    'url' => $request->fullUrl()
                ]);

                return response()->json([
                    "success" => false,
                    "message" => "Too many requests. Please wait a moment and try again.",
                    "retry_after" => 60
                ], 429); // 429 Too Many Requests (more appropriate than 503)
            }
        } catch (\Exception $e) {
            // If cache fails, allow the request to proceed
            Log::error('Circuit breaker cache error: ' . $e->getMessage());
        }

        return $next($request);
    }
}
