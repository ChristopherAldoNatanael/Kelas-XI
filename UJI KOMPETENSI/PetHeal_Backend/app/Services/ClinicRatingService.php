<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Doctor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * MULTI-CLINIC DISCOVERY: agregasi rating klinik dari review dokter.
 *
 * Sebelumnya `clinics.rating` dan `clinics.reviews_count` hanya kolom pasif
 * (tidak pernah diisi), sehingga "urutkan rating tertinggi" dan filter
 * "rating minimum" selalu kosong. Sekarang keduanya dihitung ulang dari
 * `doctor_reviews` milik dokter di klinik tersebut:
 *
 *   clinics.rating        = ROUND(AVG(doctor_reviews.rating), 2)
 *   clinics.reviews_count = COUNT(doctor_reviews.id)
 *
 * Rating `NULL` + count 0 berarti "belum ada ulasan" (bukan 0 bintang), supaya
 * UI bisa menampilkan status "belum ada rating" alih-alih angka karangan.
 */
class ClinicRatingService
{
    /** Recalculate rating satu klinik. Tidak melakukan apa pun untuk id null. */
    public static function refresh(?int $clinicId): void
    {
        if (!$clinicId) {
            return;
        }

        $row = DB::table('doctor_reviews')
            ->join('doctors', 'doctors.id', '=', 'doctor_reviews.doctor_id')
            ->where('doctors.clinic_id', $clinicId)
            ->selectRaw('AVG(doctor_reviews.rating) as avg_rating, COUNT(doctor_reviews.id) as total')
            ->first();

        $total = (int) ($row->total ?? 0);
        $average = ($row && $row->avg_rating !== null) ? round((float) $row->avg_rating, 2) : null;

        Clinic::whereKey($clinicId)->update([
            'rating' => $average,
            'reviews_count' => $total,
        ]);

        // Daftar klinik publik di-cache; rating baru harus ikut berubah.
        Cache::forget('public_clinics_index');
    }

    /** Recalculate rating klinik pemilik dokter tertentu. */
    public static function refreshForDoctor(?int $doctorId): void
    {
        if (!$doctorId) {
            return;
        }

        $clinicId = Doctor::whereKey($doctorId)->value('clinic_id');
        self::refresh($clinicId ? (int) $clinicId : null);
    }

    /**
     * Backfill semua klinik (dipakai migration/artisan bila perlu).
     * Mengembalikan jumlah klinik yang diproses.
     */
    public static function refreshAll(): int
    {
        $count = 0;
        Clinic::query()->select('id')->orderBy('id')->chunkById(100, function ($clinics) use (&$count) {
            foreach ($clinics as $clinic) {
                self::refresh((int) $clinic->id);
                $count++;
            }
        });

        return $count;
    }
}
