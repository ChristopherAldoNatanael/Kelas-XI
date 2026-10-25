<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Support\Facades\Auth;

/**
 * Basis semua controller portal dokter.
 *
 * Satu tempat untuk mengambil profil dokter milik sesi aktif, sehingga setiap
 * query di portal bisa di-scope ke `doctor_id` + `clinic_id` tanpa mengulang
 * pengecekan role di belakang controller.
 */
abstract class DoctorPortalController extends Controller
{
    protected function doctor(): Doctor
    {
        $user = Auth::user();
        $doctor = $user?->doctor;

        abort_unless($doctor, 403, 'Profil dokter tidak ditemukan untuk akun ini.');

        // Sabuk pengaman kedua: akun dan profil praktik wajib berada di klinik
        // yang sama. Middleware `doctor.auth` sudah memeriksa ini, tetapi
        // controller tetap menolak bila suatu saat ada route yang lupa memakai
        // middleware tersebut.
        abort_unless(
            (int) $doctor->clinic_id === (int) $user->clinic_id,
            403,
            'Profil dokter tidak berada di klinik akun ini.'
        );

        return $doctor;
    }
}
