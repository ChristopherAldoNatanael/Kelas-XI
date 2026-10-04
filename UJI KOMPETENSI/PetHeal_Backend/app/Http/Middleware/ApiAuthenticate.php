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
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Try to authenticate with Sanctum
        if (!auth('sanctum')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Set the user for the request
        $user = auth('sanctum')->user();
        auth()->setUser($user);

        // PHASE 1 (D1, fail-closed): tenant roles must be bound to a clinic.
        // Previously a user with clinic_id = NULL silently bypassed every
        // `if ($clinicId)` filter and could read/book across all clinics.
        // super_admin keeps existing behavior (may operate without clinic).
        // PHASE 3 (F-03): the bound clinic must also be ACTIVE.
        // PHASE 7 fix: self account-deletion bypasses BOTH tenant checks
        // below. AuthController@deleteAccount is strictly self-scoped (only
        // the caller's own rows + own tokens), so tenant binding/mismatch
        // must never trap a user inside an account they cannot remove —
        // whether clinic-less, suspended, or carrying a stale slug header.
        // Bearer + Sanctum authentication above still applies.
        $isSelfDelete = $request->isMethod('delete') && $request->is('api/auth/account');
        if ($isSelfDelete) {
            return $next($request);
        }

        if ($user->role !== 'super_admin') {
            $clinic = $user->clinic;
            if (!$user->clinic_id || !$clinic || !$clinic->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Akun Anda tidak terikat pada klinik yang aktif.',
                ], 403);
            }
        }

        // Validate X-Clinic-Slug header if present
        $clinicSlug = $request->header('X-Clinic-Slug');
        if ($clinicSlug && $user->clinic) {
            // User has a clinic — header must match their clinic
            if ($user->clinic->slug !== $clinicSlug) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Clinic slug tidak sesuai dengan akun Anda.',
                ], 403);
            }
        } elseif ($clinicSlug && !$user->clinic && $user->role !== 'super_admin') {
            // User without clinic sending a header — reject
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak terikat pada klinik manapun.',
            ], 403);
        }

        return $next($request);
    }
}
