<?php

namespace App\Services;

use App\Models\User;

/**
 * MULTI-CLINIC DISCOVERY: satu tempat untuk menentukan apakah sebuah akun
 * adalah PASIEN (akun global lintas klinik) atau STAF KLINIK (tenant-scoped).
 *
 * - Pasien (`role = user`): boleh membaca katalog & jadwal klinik mana pun,
 *   dan memilih klinik berbeda pada setiap booking. `users.clinic_id` yang
 *   masih tersisa dari sistem lama TIDAK dipakai sebagai otorisasi.
 * - Staf (clinic_admin, admin, doctor): tetap terkurung pada kliniknya.
 * - super_admin: bypass, tanpa klinik (kecuali memilih konteks di web).
 */
class PatientScope
{
    public static function isPatient(?User $user): bool
    {
        return $user !== null && ($user->role ?? null) === 'user';
    }

    /**
     * Batasi query untuk pembacaan katalog publik (dokter, layanan).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function scopeCatalog($query, ?User $user, ?int $requestedClinicId = null, string $column = 'clinic_id')
    {
        if ($requestedClinicId) {
            return $query->where($column, $requestedClinicId);
        }

        if (self::isPatient($user)) {
            // Pasien global: katalog lintas klinik (baca saja).
            return $query;
        }

        if ($user && $user->clinic_id) {
            return $query->where($column, (int) $user->clinic_id);
        }

        if ($user && ($user->role ?? null) === 'super_admin') {
            return $query;
        }

        // Staf tanpa klinik / tamu tanpa slug: fail-closed.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Kunci cache pembeda scope katalog supaya baris antar klinik tidak
     * bocor lewat cache.
     */
    public static function catalogCacheKey(?User $user, ?int $requestedClinicId = null): string
    {
        if ($requestedClinicId) {
            return 'c' . $requestedClinicId;
        }

        if ($user && ($user->role ?? null) === 'super_admin') {
            return 'all';
        }

        if ($user && ! self::isPatient($user)) {
            return $user->clinic_id ? 'c' . (int) $user->clinic_id : 'none';
        }

        return 'all';
    }
}
