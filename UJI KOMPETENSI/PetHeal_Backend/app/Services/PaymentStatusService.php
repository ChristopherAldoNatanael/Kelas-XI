<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentStatusService
{
    private function clearPaymentCaches(?int $clinicId = null): void
    {
        // PHASE 3 (B12): forget the keys readers actually use
        // (Admin/PaymentController stats + the cached all-time dashboard).
        // Date-ranged dashboards are intentionally not cached, so there is no
        // per-range key to invalidate.
        $suffix = $clinicId ?: 'all';
        Cache::forget('payment_stats_c' . $suffix);
        Cache::forget('dashboard_stats_v4_c' . $suffix . '_all');
    }

    /**
     * Selaraskan biaya tambahan rekam medis dengan uang yang sudah masuk ke
     * booking-nya (lihat MedicalRecord::syncExtraPaymentForBooking).
     */
    private function syncMedicalRecordPaymentState(Booking $booking): void
    {
        MedicalRecord::syncExtraPaymentForBooking($booking);
    }

    /**
     * Apply a Midtrans transaction result to a medical-record extra payment.
     *
     * @return array{updated:bool,duplicate:bool,medical_record:MedicalRecord}
     */
    public function applyMedicalRecordTransactionStatus(
        MedicalRecord $record,
        string $orderId,
        string $transactionStatus,
        ?string $paymentType,
        float|int|string $grossAmount,
        array $payload = []
    ): array {
        return DB::transaction(function () use (
            $record,
            $orderId,
            $transactionStatus,
            $paymentType,
            $grossAmount,
            $payload
        ) {
            $record = MedicalRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $normalizedStatus = strtolower(trim($transactionStatus));
            $fraudStatus = strtolower(trim((string) ($payload['fraud_status'] ?? '')));
            $gross = (float) $grossAmount;

            // PHASE 3 (B3): ignore non-positive amounts (Midtrans-authentic
            // payloads never carry these; guards against corrupt delivery).
            if ($gross <= 0 && ($normalizedStatus === 'settlement'
                || ($normalizedStatus === 'capture' && ($fraudStatus === '' || $fraudStatus === 'accept')))) {
                \Illuminate\Support\Facades\Log::warning('Ignoring non-positive payment amount', [
                    'order_id' => $orderId,
                    'gross' => $grossAmount,
                ]);

                return [
                    'updated' => false,
                    'duplicate' => false,
                    'medical_record' => $record->fresh(),
                ];
            }

            $isSuccessful = $normalizedStatus === 'settlement'
                || ($normalizedStatus === 'capture' && ($fraudStatus === '' || $fraudStatus === 'accept'));

            if ($isSuccessful) {
                $created = DB::table('payment_events')->insertOrIgnore([
                    'booking_id' => $record->booking_id,
                    'medical_record_id' => $record->id,
                    'order_id' => $orderId,
                    'transaction_status' => $normalizedStatus,
                    'payment_type' => $paymentType,
                    'gross_amount' => $gross,
                    'payload' => json_encode($payload),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($created === 0) {
                    return [
                        'updated' => false,
                        'duplicate' => true,
                        'medical_record' => $record->fresh(),
                    ];
                }

                $paidAmount = (float) $record->extra_payment_paid_amount + $gross;
                $extraAmount = (float) $record->extra_payment_amount;

                $record->update([
                    'extra_payment_paid_amount' => $paidAmount,
                    'extra_payment_status' => $paidAmount >= $extraAmount ? 'paid' : 'partial',
                    'extra_payment_order_id' => $orderId,
                    'extra_payment_date' => now(),
                ]);
                $this->clearPaymentCaches($record->clinic_id);

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'medical_record' => $record->fresh(),
                ];
            }

            if ($normalizedStatus === 'pending') {
                // PHASE 3 (B2): never downgrade money already recorded. A
                // later token's `pending` must not reset paid/partial back.
                if ((float) $record->extra_payment_paid_amount > 0
                    || $record->extra_payment_status === 'paid') {
                    return [
                        'updated' => false,
                        'duplicate' => false,
                        'medical_record' => $record->fresh(),
                    ];
                }

                $record->update([
                    'extra_payment_status' => 'pending',
                    'extra_payment_order_id' => $orderId,
                ]);
                $this->clearPaymentCaches($record->clinic_id);

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'medical_record' => $record->fresh(),
                ];
            }

            if (in_array($normalizedStatus, ['cancel', 'expire', 'deny', 'failure'], true)) {
                // PHASE 3 (B2): a stale/duplicate terminal event for an old
                // order must not flip an already-paid record to failed.
                if ((float) $record->extra_payment_paid_amount > 0
                    || $record->extra_payment_status === 'paid') {
                    return [
                        'updated' => false,
                        'duplicate' => false,
                        'medical_record' => $record->fresh(),
                    ];
                }

                $record->update([
                    'extra_payment_status' => 'failed',
                    'extra_payment_order_id' => $orderId,
                ]);
                $this->clearPaymentCaches($record->clinic_id);

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'medical_record' => $record->fresh(),
                ];
            }

            return [
                'updated' => false,
                'duplicate' => false,
                'medical_record' => $record->fresh(),
            ];
        });
    }

    /**
     * Apply a Midtrans transaction result to a booking in a single transaction.
     *
     * @return array{updated:bool,duplicate:bool,booking:Booking}
     */
    public function applyTransactionStatus(
        Booking $booking,
        string $orderId,
        string $transactionStatus,
        ?string $paymentType,
        float|int|string $grossAmount,
        array $payload = []
    ): array {
        return DB::transaction(function () use (
            $booking,
            $orderId,
            $transactionStatus,
            $paymentType,
            $grossAmount,
            $payload
        ) {
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $normalizedStatus = strtolower(trim($transactionStatus));
            $normalizedPaymentType = $paymentType ?: $booking->payment_type;
            $fraudStatus = strtolower(trim((string) ($payload['fraud_status'] ?? '')));
            $gross = (float) $grossAmount;

            // PHASE 3 (B3): ignore non-positive amounts.
            $isSuccessful = $normalizedStatus === 'settlement'
                || ($normalizedStatus === 'capture' && ($fraudStatus === '' || $fraudStatus === 'accept'));

            if ($isSuccessful && $gross <= 0) {
                \Illuminate\Support\Facades\Log::warning('Ignoring non-positive payment amount', [
                    'order_id' => $orderId,
                    'gross' => $grossAmount,
                ]);

                return [
                    'updated' => false,
                    'duplicate' => false,
                    'booking' => $booking->fresh(),
                ];
            }

            // PHASE 3 (B1/B3): terminal/advanced states are monotonic. A paid
            // booking stays paid; dp_paid/partial are never reset by a later
            // pending/failed event for another order of the same booking.
            // Status refund ikut "terminal": notifikasi `pending`/`failure` untuk
            // transaksi lain tidak boleh menghapus kewajiban refund yang sudah ada.
            $terminalStatuses = ['paid', Booking::PAYMENT_REFUNDED];
            $advancedStatuses = ['dp_paid', 'partial', Booking::PAYMENT_REFUND_PENDING];

            if ($isSuccessful) {
                $created = DB::table('payment_events')->insertOrIgnore([
                    'booking_id' => $booking->id,
                    'order_id' => $orderId,
                    'transaction_status' => $normalizedStatus,
                    'payment_type' => $normalizedPaymentType,
                    'gross_amount' => $gross,
                    'payload' => json_encode($payload),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($created === 0) {
                    return [
                        'updated' => false,
                        'duplicate' => true,
                        'booking' => $booking->fresh(),
                    ];
                }

                // Jumlah yang dikirim Midtrans dicatat apa adanya. Pengecekan
                // terhadap sisa tagihan dilakukan di bawah, SETELAH event
                // dicatat, supaya:
                //  1. event tidak hilang dari payment_events (jejak audit), dan
                //  2. event duplikat tetap menghasilkan respons yang sama
                //     (idempoten) saat Midtrans mengirim ulang.
                $totalAmount = (float) $booking->total_amount;
                $rawPaidAmount = (float) $booking->paid_amount + $gross;

                // `paid_amount` tidak boleh melewati nilai tagihan. Pernah terjadi
                // dua transaksi untuk satu booking (DP lalu pelunasan penuh) dan
                // akumulasi ini menghasilkan paid_amount > total_amount, sehingga
                // laporan keuangan dan hitungan refund ikut membengkak. Uang
                // berlebih tetap tercatat di payment_events + log, tetapi kolom
                // booking dibatasi pada nilai tagihan agar selisihnya kelihatan.
                if ($totalAmount > 0 && $rawPaidAmount > $totalAmount + 0.005) {
                    Log::warning('Pembayaran melebihi nilai tagihan booking', [
                        'booking_id' => $booking->id,
                        'clinic_id' => $booking->clinic_id,
                        'total_amount' => $totalAmount,
                        'accumulated' => $rawPaidAmount,
                        'gross' => $gross,
                        'overpaid' => round($rawPaidAmount - $totalAmount, 2),
                    ]);

                    $currentPaidAmount = $totalAmount;
                } else {
                    $currentPaidAmount = $rawPaidAmount;
                }

                $remainingAmount = max(0, $totalAmount - $currentPaidAmount);

                // Dana masuk untuk booking yang SUDAH dibatalkan. Uangnya nyata,
                // jadi tetap dicatat di `paid_amount` + `payment_events`, tetapi
                // tidak boleh berubah menjadi pendapatan klinik dan tidak boleh
                // menjadi tagihan sisa: seluruhnya menjadi kewajiban refund.
                // Dulu percabangan ini berjalan seperti pembayaran normal dan
                // menulis `payment_status = paid`, sehingga booking batal terlihat
                // lunas tanpa satu baris pun yang menyatakan uang harus kembali.
                if ($booking->status === 'cancelled') {
                    Log::warning('Pembayaran diterima untuk booking yang sudah dibatalkan', [
                        'booking_id' => $booking->id,
                        'clinic_id' => $booking->clinic_id,
                        'order_id' => $orderId,
                        'gross' => $gross,
                        'paid_now' => $currentPaidAmount,
                    ]);

                    $booking->update([
                        'paid_amount' => $currentPaidAmount,
                        'remaining_amount' => 0,
                        'payment_date' => now(),
                    ]);

                    $booking->syncRefundPaymentStatus();
                    $this->syncMedicalRecordPaymentState($booking);
                    $this->clearPaymentCaches($booking->clinic_id);

                    return [
                        'updated' => true,
                        'duplicate' => false,
                        'booking' => $booking->fresh(),
                    ];
                }

                $updateData = [
                    'paid_amount' => $currentPaidAmount,
                    'remaining_amount' => $remainingAmount,
                    'payment_date' => now(),
                ];

                if ($remainingAmount <= 0) {
                    $updateData['payment_status'] = 'paid';
                } elseif ($booking->payment_type === 'dp'
                    && (float) $booking->paid_amount == 0.0
                    && $gross >= (float) $booking->dp_amount) {
                    // PHASE 3 (B3): dp_paid requires the DP leg to actually
                    // cover the DP amount — a short payment stays `partial`.
                    $updateData['payment_status'] = 'dp_paid';
                    $updateData['payment_type'] = 'dp';
                } else {
                    $updateData['payment_status'] = 'partial';
                }

                $booking->update($updateData);

                // Nilai "kekurangan biaya" pada rekam medis dihitung dari
                // `total_medical_cost - booking.paid_amount`. Sebelumnya angka itu
                // hanya dihitung ulang saat rekam medis dibuat/diedit, jadi
                // pembayaran booking yang masuk SETELAH rekam medis dibuat tidak
                // pernah menurunkannya: pasien yang sudah DP lalu melunasi tetap
                // ditagih kekurangan sebesar DP-nya (total tagihan melebihi biaya).
                $this->syncMedicalRecordPaymentState($booking);

                $this->clearPaymentCaches($booking->clinic_id);

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'booking' => $booking->fresh(),
                ];
            }

            if ($normalizedStatus === 'pending') {
                // PHASE 3 (B1): never downgrade recorded money.
                if (in_array($booking->payment_status, array_merge($terminalStatuses, $advancedStatuses), true)) {
                    return [
                        'updated' => false,
                        'duplicate' => false,
                        'booking' => $booking->fresh(),
                    ];
                }

                $booking->update([
                    'payment_status' => 'pending',
                ]);
                $this->clearPaymentCaches($booking->clinic_id);

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'booking' => $booking->fresh(),
                ];
            }

            if (in_array($normalizedStatus, ['cancel', 'expire', 'deny', 'failure'], true)) {
                // PHASE 3 (B1): a stale terminal event for an old order must
                // not flip paid/dp_paid/partial back to failed.
                if (in_array($booking->payment_status, array_merge($terminalStatuses, $advancedStatuses), true)) {
                    return [
                        'updated' => false,
                        'duplicate' => false,
                        'booking' => $booking->fresh(),
                    ];
                }

                $booking->update([
                    'payment_status' => 'failed',
                ]);
                $this->clearPaymentCaches($booking->clinic_id);

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'booking' => $booking->fresh(),
                ];
            }

            return [
                'updated' => false,
                'duplicate' => false,
                'booking' => $booking->fresh(),
            ];
        });
    }
}
