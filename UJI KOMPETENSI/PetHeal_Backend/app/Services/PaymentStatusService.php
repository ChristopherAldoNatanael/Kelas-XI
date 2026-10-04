<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentStatusService
{
    private function clearPaymentCaches(?int $clinicId = null): void
    {
        // PHASE 3 (B12): forget the keys readers actually use
        // (Admin/PaymentController stats + admin dashboard `_all` variant).
        // Date-ranged dashboard keys cannot be enumerated here — documented.
        $suffix = $clinicId ?: 'all';
        Cache::forget('payment_stats_c' . $suffix);
        Cache::forget('dashboard_stats_c' . $suffix . '_all');
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
            $terminalStatuses = ['paid'];
            $advancedStatuses = ['dp_paid', 'partial'];

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

                $currentPaidAmount = (float) $booking->paid_amount + $gross;
                $remainingAmount = max(0, (float) $booking->total_amount - $currentPaidAmount);

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
