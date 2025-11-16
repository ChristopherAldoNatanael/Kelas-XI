<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            // Jika request dari web browser, redirect ke login
            if (!$request->wantsJson() && !$request->is('api/*')) {
                return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
            }

            // Untuk API request, return JSON
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Authentication required'
            ], 401);
        }

        $user = Auth::user();

        // Jika role berisi koma, berarti multiple roles
        $allowedRoles = explode(',', $role);

        if (!in_array($user->role, $allowedRoles)) {
            // Jika request dari web browser, redirect dengan error
            if (!$request->wantsJson() && !$request->is('api/*')) {
                return back()->with('error', 'Anda tidak memiliki akses untuk melakukan tindakan ini.');
            }

            // Untuk API request, return JSON
            return response()->json([
                'success' => false,
                'message' => 'Forbidden - Insufficient permissions',
                'required_roles' => $allowedRoles,
                'user_role' => $user->role
            ], 403);
        }

        return $next($request);
    }
}
