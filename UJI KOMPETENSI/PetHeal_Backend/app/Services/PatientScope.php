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
     * "Pasien klinik" = akun pasien yang punya transaksi di klinik tersebut.
     *
     * Sejak revisi multi-klinik, pasien adalah akun GLOBAL: `users.clinic_id`
     * miliknya NULL (atau klinik lama dari sistem sebelumnya). Jadi
     * kepemilikan akun TIDAK BISA dipakai untuk menentukan pasien siapa.
     * Satu-satunya bukti adalah transaksi: booking atau rekam medis.
     *
     * Dipakai oleh Admin\UserController (daftar, detail, edit, hapus) dan
     * Admin\DashboardController (statistik & pencarian global) supaya kedua
     * layar memakai definisi yang sama persis.
     */
    public static function clinicPatientIdsQuery(int $clinicId): \Illuminate\Database\Query\Builder
    {
        return \Illuminate\Support\Facades\DB::table('bookings')
            ->select('user_id')
            ->where('clinic_id', $clinicId)
            ->whereNotNull('user_id')
            ->union(
                \Illuminate\Support\Facades\DB::table('medical_records')
                    ->join('bookings', 'bookings.id', '=', 'medical_records.booking_id')
                    ->select('bookings.user_id')
                    ->where('medical_records.clinic_id', $clinicId)
                    ->whereNotNull('bookings.user_id')
            );
    }

    /**
     * Batasi query akun ke "yang boleh dilihat klinik ini": staf milik klinik
     * ATAU pasien yang pernah bertransaksi di klinik itu.
     *
     * Tanpa clinicId (overview super_admin) query tidak dibatasi.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function scopeClinicUsers($query, ?int $clinicId, string $idColumn = 'users.id', string $clinicColumn = 'users.clinic_id')
    {
        if (! $clinicId) {
            return $query;
        }

        return $query->where(function ($q) use ($clinicId, $idColumn, $clinicColumn) {
            $q->where($clinicColumn, $clinicId)
                ->orWhereIn($idColumn, self::clinicPatientIdsQuery($clinicId));
        });
    }

    /**
     * Versi untuk query hewan: hewan milik pasien klinik (relasi `user`).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeClinicPets($query, ?int $clinicId)
    {
        if (! $clinicId) {
            return $query;
        }

        return $query->whereHas('user', fn ($userQuery) => self::scopeClinicUsers($userQuery, $clinicId));
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
