<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga portal dokter.
 *
 * Berbeda dari AdminAuth: role yang boleh lewat hanya `doctor`, dan akun harus
 * benar-benar terhubung ke baris `doctors` milik klinik yang aktif. Admin yang
 * salah masuk ke /doctor diarahkan ke dasbor admin (tanpa logout), sedangkan
 * role lain yang tidak berhak diakhiri sesinya supaya tidak tersangkut.
 */
class DoctorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('doctor.login');
        }

        $user = Auth::user();

        if ($user->role !== 'doctor') {
            if (in_array($user->role, ['super_admin', 'clinic_admin', 'admin'], true)) {
                return redirect()->route('admin.dashboard');
            }

            Auth::logout();

            return redirect()->route('doctor.login')
                ->withErrors(['email' => 'Akun ini tidak memiliki akses portal dokter.']);
        }

        $clinic = $user->clinic;
        $doctor = $user->doctor;

        if (!$user->clinic_id || !$clinic || !$clinic->is_active || !$doctor) {
            Auth::logout();

            return redirect()->route('doctor.login')
                ->withErrors(['email' => !$doctor
                    ? 'Akun dokter belum terhubung ke profil praktik. Hubungi admin klinik Anda.'
                    : __('auth.inactive_clinic')]);
        }

        // Akun dan baris `doctors` harus satu klinik. Tanpa cek ini, tautan
        // lintas klinik (mis. hasil manipulasi data) bisa membuka data klinik
        // lain karena semua query portal memakai `$doctor->clinic_id`.
        if ((int) $doctor->clinic_id !== (int) $user->clinic_id) {
            Auth::logout();

            return redirect()->route('doctor.login')
                ->withErrors(['email' => 'Profil dokter tidak berada di klinik akun ini. Hubungi admin klinik Anda.']);
        }

        return $next($request);
    }
}
