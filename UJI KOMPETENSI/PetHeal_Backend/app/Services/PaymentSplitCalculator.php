<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Kalkulator pembagian dana (Midtrans `transaction_split_settings`).
 *
 * Aturan:
 * - Nominal integer rupiah, tanpa float.
 * - Pembulatan komisi platform memakai HALF_UP; sisa selalu menjadi bagian
 *   klinik sehingga `platform + clinic === gross` tepat.
 * - Persentase komisi TIDAK di-hardcode; nilainya berasal dari konfigurasi
 *   klinik yang disetujui.
 * - Jika komisi > 0 tetapi merchant platform belum dikonfigurasi, transaksi
 *   DITOLAK — dana tidak boleh diam-diam masuk seluruhnya ke klinik atau
 *   sebaliknya.
 */
class PaymentSplitCalculator
{
    /**
     * @return array{
     *   gross:int,
     *   clinic_amount:int,
     *   platform_amount:int,
     *   commission_percent:float,
     *   settings:?array,
     *   currency:string
     * }
     */
    public function calculate(
        int $gross,
        float|int|string $commissionPercent,
        string $clinicMerchantId,
        ?string $platformMerchantId
    ): array {
        if ($gross <= 0) {
            throw new InvalidArgumentException('Gross amount harus lebih dari 0.');
        }

        if (trim($clinicMerchantId) === '') {
            throw new InvalidArgumentException('Merchant ID klinik wajib tersedia untuk pembagian dana.');
        }

        $percent = (float) $commissionPercent;

        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Persentase komisi harus di antara 0 dan 100.');
        }

        $platformAmount = (int) round($gross * $percent / 100, 0, PHP_ROUND_HALF_UP);
        $clinicAmount = $gross - $platformAmount;

        if ($platformAmount < 0 || $clinicAmount < 0) {
            throw new InvalidArgumentException('Hasil pembagian dana tidak valid.');
        }

        if ($platformAmount > 0 && blank($platformMerchantId)) {
            throw new InvalidArgumentException('Merchant ID platform belum dikonfigurasi; komisi tidak dapat dibebankan.');
        }

        $rules = [
            ['merchant_id' => $clinicMerchantId, 'value' => (string) $clinicAmount],
        ];

        if ($platformAmount > 0) {
            $rules[] = ['merchant_id' => (string) $platformMerchantId, 'value' => (string) $platformAmount];
        }

        return [
            'gross' => $gross,
            'clinic_amount' => $clinicAmount,
            'platform_amount' => $platformAmount,
            'commission_percent' => $percent,
            'currency' => 'IDR',
            'settings' => [
                'gross_amount_split' => [
                    'type' => 'fixed',
                    'split_rules' => $rules,
                    'remaining_split_receiver' => $clinicMerchantId,
                ],
            ],
        ];
    }
}
