<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentStatusService
{
    private function clearPaymentCaches(): void
    {
        Cache::forget('payment_stats');
        Cache::forget('dashboard_stats_all');
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
            $gross = (float) $grossAmount;

            if (in_array($normalizedStatus, ['settlement', 'capture'], true)) {
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
                } elseif ($booking->payment_type === 'dp' && (float) $booking->paid_amount == 0.0) {
                    $updateData['payment_status'] = 'dp_paid';
                    $updateData['payment_type'] = 'dp';
                } else {
                    $updateData['payment_status'] = 'partial';
                }

                $booking->update($updateData);
                $this->clearPaymentCaches();

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'booking' => $booking->fresh(),
                ];
            }

            if ($normalizedStatus === 'pending') {
                $booking->update([
                    'payment_status' => 'pending',
                ]);
                $this->clearPaymentCaches();

                return [
                    'updated' => true,
                    'duplicate' => false,
                    'booking' => $booking->fresh(),
                ];
            }

            if (in_array($normalizedStatus, ['cancel', 'expire', 'deny', 'failure'], true)) {
                $booking->update([
                    'payment_status' => 'failed',
                ]);
                $this->clearPaymentCaches();

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
