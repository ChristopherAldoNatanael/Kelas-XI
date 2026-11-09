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
        //
        // OTP hapus akun & ganti email ikut bypass: keduanya hanya menyentuh
        // baris milik pemanggil sendiri, dan justru harus tetap bisa dipakai
        // saat klinik dinonaktifkan supaya user tidak terjebak tanpa jalan
        // keluar dari akunnya.
        $isSelfAccountAction = $request->is('api/auth/account', 'api/auth/account/*', 'api/auth/email/change/*');
        if ($isSelfAccountAction) {
            return $next($request);
        }

        // MULTI-CLINIC DISCOVERY (2026-11-09): pasien (`user`) adalah akun
        // GLOBAL. Sejak revisi ini user mendaftar tanpa memilih klinik dan
        // memilih klinik per booking, sehingga `users.clinic_id` NULL harus
        // diizinkan (sebelumnya 403 di semua route terproteksi).
        // Staf klinik (clinic_admin/doctor/admin) tetap WAJIB terikat ke
        // klinik aktif yang sama — isolasi tenant tidak dilonggarkan.
        // super_admin tetap boleh beroperasi tanpa klinik (overview sistem).
        if (! $this->isPatient($user) && ($user->role ?? null) !== 'super_admin') {
            $clinic = $user->clinic;
            if (!$user->clinic_id || !$clinic || !$clinic->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses tidak diizinkan. Akun anda tidak terhubung ke klinik yang aktif.',
                ], 403);
            }
        }

        // X-Clinic-Slug hanya penanda konteks TENANT (staf klinik). Pasien
        // boleh mengirim slug klinik mana pun yang akan dibooking, dan nilai
        // header tidak pernah dipakai sebagai sumber otorisasi.
        $clinicSlug = $request->header('X-Clinic-Slug');
        if ($clinicSlug && ! $this->isPatient($user) && $user->clinic) {
            // Staff must stay inside their own clinic
            if ($user->clinic->slug !== $clinicSlug) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Clinic slug tidak sesuai dengan akun Anda.',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Pasien = akun global yang tidak dikelola klinik (role `user`).
     * Role lain (super_admin, clinic_admin, admin, doctor) tetap tenant-scoped.
     */
    private function isPatient($user): bool
    {
        return ($user->role ?? null) === 'user';
    }
}
