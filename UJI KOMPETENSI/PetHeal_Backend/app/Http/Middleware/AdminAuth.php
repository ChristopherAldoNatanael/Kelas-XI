<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('admin.login');
        }

        // Allow super_admin, clinic_admin, and legacy admin
        $allowedRoles = ['super_admin', 'clinic_admin', 'admin'];
        if (!in_array(Auth::user()->role, $allowedRoles)) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Anda tidak memiliki akses admin.']);
        }

        // PHASE 1 (D1, fail-closed): tenant roles must be bound to a clinic.
        // Previously a tenant user with clinic_id = NULL silently inherited
        // the super_admin overview (every `when($clinicId, ...)` became unscoped).
        // PHASE 3 (F-03): the clinic must also be ACTIVE — suspending a
        // clinic (toggleActive) must actually stop its admins. super_admin
        // without clinic keeps overview (existing behavior).
        if (Auth::user()->role !== 'super_admin') {
            $clinic = Auth::user()->clinic;
            if (!Auth::user()->clinic_id || !$clinic || !$clinic->is_active) {
                Auth::logout();
                return redirect()->route('admin.login')
                    ->withErrors(['email' => __('auth.inactive_clinic')]);
            }
        }

        return $next($request);
    }
}
