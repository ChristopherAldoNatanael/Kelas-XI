<?php

namespace App\Support;

/**
 * Operasi uang yang konsisten untuk seluruh lapisan.
 *
 * Kolom database uang bertipe `decimal(10,2)` tetapi PHP lebih aman
 * berhitung dalam RUPIAH INTEGER. Semua perbandingan nominal — DP, komisi,
 * sisa tagihan, refund — harus melewati satu-satunya titik normalisasi ini
 * supaya tidak ada selisih 1 rupiah yang bergantung pada pembulatan float.
 */
class Money
{
    /**
     * Normalisasi sembarang nilai (float/string/int) menjadi rupiah integer.
     *
     * `decimal(10,2)` keluar dari MySQL sebagai string "150000.00"; nilai
     * aritmetika DP bisa jadi "75000.0". Satu-satunya cara aman adalah
     * membulatkan sekali, HALF_UP, di sini.
     */
    public static function rupiah(mixed $value): int
    {
        return (int) round((float) $value);
    }

    /**
     * Persentase dari suatu nilai (rupiah integer) dengan pembulatan HALF_UP.
     *
     * Contoh: 50% dari 150.000 → 75.000; 50% dari 33.333 → 16.667 (bukan
     * 16.666,5). Sisa selalu dipegang pihak yang menerima nilai penuh.
     */
    public static function percentOf(int $gross, float $percent): int
    {
        return (int) round($gross * $percent / 100, 0, PHP_ROUND_HALF_UP);
    }

    /** Konversi aman dari rupiah integer kembali ke float (untuk field float). */
    public static function toFloat(int $rupiah): float
    {
        return (float) $rupiah;
    }
}