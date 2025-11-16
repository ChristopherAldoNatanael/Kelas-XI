<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestThrottling
{
    /**
     * Handle an incoming request with advanced throttling
     */
    public function handle(Request $request, Closure $next, string $maxRequests = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = (int) $maxRequests;
        $decaySeconds = (int) $decayMinutes * 60;

        // Check current request count
        $attempts = Cache::get($key, 0);

        // If exceeded limit, return error immediately
        if ($attempts >= $maxAttempts) {
            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'user' => auth('sanctum')->user()?->nama ?? 'guest',
                'attempts' => $attempts,
                'limit' => $maxAttempts
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak request. Silakan tunggu sebentar.',
                'retry_after' => Cache::get($key . ':timer') ?: $decaySeconds
            ], 429, [
                'Retry-After' => $decaySeconds,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0
            ]);
        }

        // Increment counter
        Cache::put($key, $attempts + 1, $decaySeconds);

        // Set timer for retry-after header
        if (!Cache::has($key . ':timer')) {
            Cache::put($key . ':timer', now()->addSeconds($decaySeconds)->timestamp, $decaySeconds);
        }

        $response = $next($request);

        // Add rate limit headers to response
        $remaining = max(0, $maxAttempts - $attempts - 1);
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', Cache::get($key . ':timer') ?: (time() + $decaySeconds));

        return $response;
    }

    /**
     * Resolve request signature for throttling
     */
    protected function resolveRequestSignature(Request $request): string
    {
        // Use IP + user ID + route for more granular throttling
        $userId = auth('sanctum')->id() ?: 'guest';
        $route = $request->route() ? $request->route()->getName() : $request->path();

        return sha1($request->ip() . '|' . $userId . '|' . $route);
    }
}
