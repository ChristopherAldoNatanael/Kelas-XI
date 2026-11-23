<?php

namespace App\Support;

/**
 * Tautan "chat WhatsApp" tanpa API pihak ketiga.
 *
 * Dipakai saat klinik harus menghubungi pasien untuk mengembalikan dana
 * (meminta nama pemilik rekening + nomor rekening/e-wallet). Nomor Indonesia
 * selalu dinormalkan ke format internasional `62…` karena tautan `wa.me`
 * menolak awalan `0` maupun `+`.
 */
class WhatsApp
{
    /**
     * Ubah nomor apa pun yang masuk akal menjadi digit internasional.
     *
     * Contoh: `0812-3456-7890` → `6281234567890`,
     * `+62 812 3456 7890` → `6281234567890`, `81234567890` → `6281234567890`.
     * Nomor yang terlalu pendek/panjang atau tidak berisi digit → `null`,
     * supaya UI bisa menampilkan "nomor belum ada" daripada tautan rusak.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Awalan internasional dengan nol yang tersisa (`+620812…`).
        $digits = preg_replace('/^62?0/', '62', $digits, 1) ?? $digits;

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        } elseif (! str_starts_with($digits, '62')) {
            // Nomor luar negeri: biarkan apa adanya bila panjangnya wajar.
            if (strlen($digits) < 9 || strlen($digits) > 15) {
                return null;
            }

            return $digits;
        }

        return (strlen($digits) >= 10 && strlen($digits) <= 15) ? $digits : null;
    }

    /**
     * Tautan chat siap pakai. `$message` di-encode sehingga baris baru dan
     * karakter `&` tidak memotong pesan.
     */
    public static function link(?string $phone, ?string $message = null): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $url = 'https://wa.me/' . $normalized;
        $message = trim((string) $message);

        return $message === '' ? $url : $url . '?text=' . rawurlencode($message);
    }
}
