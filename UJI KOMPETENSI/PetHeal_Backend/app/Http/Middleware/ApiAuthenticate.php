<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for Bearer token
        if (!$request->bearerToken()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. No token provided.'
            ], 401);
        }

        // Try to authenticate with Sanctum
        if (!auth('sanctum')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Invalid token.'
            ], 401);
        }

        // Set the user for the request
        auth()->setUser(auth('sanctum')->user());

        return $next($request);
    }
}
