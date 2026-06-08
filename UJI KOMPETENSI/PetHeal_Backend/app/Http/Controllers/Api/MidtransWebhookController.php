<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;

/**
 * Midtrans Webhook Handler
 *
 * This controller handles HTTP POST notifications from Midtrans
 * when payment status changes. It verifies the signature and
 * updates the booking payment status accordingly.
 *
 * Configure this in Midtrans Merchant Portal:
 * Settings → Configuration → Payment Notification → URL
 */
class MidtransWebhookController extends Controller
{
    private function getServerKey(): string
    {
        return config('services.midtrans.server_key');
    }

    /**
     * Handle Midtrans payment notification.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            $orderId = $payload['order_id'] ?? null;
            $transactionStatus = $payload['transaction_status'] ?? null;
            $statusCode = $payload['status_code'] ?? null;
            $grossAmount = $payload['gross_amount'] ?? null;
            $signatureKey = $payload['signature_key'] ?? null;
            $paymentType = $payload['payment_type'] ?? null;

            Log::info('Midtrans webhook received', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'status_code' => $statusCode,
                'gross_amount' => $grossAmount,
                'payment_type' => $paymentType,
            ]);

            if (!$orderId) {
                return response()->json(['error' => 'Missing order_id'], 400);
            }

            // Verify signature
            $expectedSignature = hash(
                'sha512',
                $orderId . $statusCode . $grossAmount . $this->getServerKey()
            );

            if ($signatureKey !== $expectedSignature) {
                Log::warning('Invalid webhook signature', [
                    'order_id' => $orderId,
                    'has_signature' => !empty($signatureKey),
                ]);
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            // Extract booking ID from order_id (format: BOOKING-{id}-{timestamp})
            if (preg_match('/^BOOKING-(\d+)/', $orderId, $matches)) {
                $bookingId = (int) $matches[1];
                $booking = Booking::find($bookingId);

                if (!$booking) {
                    Log::warning('Booking not found for webhook', ['order_id' => $orderId]);
                    return response()->json(['error' => 'Booking not found'], 404);
                }

                // Update booking payment status based on Midtrans status
                switch ($transactionStatus) {
                    case 'settlement':
                    case 'capture':
                        $updateData = DB::transaction(function () use (
                            $bookingId,
                            $orderId,
                            $transactionStatus,
                            $paymentType,
                            $grossAmount,
                            $payload
                        ) {
                            $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();

                            $created = DB::table('payment_events')->insertOrIgnore([
                                'booking_id' => $booking->id,
                                'order_id' => $orderId,
                                'transaction_status' => $transactionStatus,
                                'payment_type' => $paymentType,
                                'gross_amount' => $grossAmount,
                                'payload' => json_encode($payload),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            if ($created === 0) {
                                return null;
                            }

                            $currentPaidAmount = (float) $booking->paid_amount + (float) $grossAmount;
                            $remainingAmount = max(0, (float) $booking->total_amount - $currentPaidAmount);

                            $updateData = [
                                'paid_amount' => $currentPaidAmount,
                                'remaining_amount' => $remainingAmount,
                            ];

                            if ($remainingAmount == 0) {
                                $updateData['payment_status'] = 'paid';
                            } elseif ($booking->payment_type === 'dp' && (float) $booking->paid_amount == 0) {
                                $updateData['payment_status'] = 'dp_paid';
                                $updateData['payment_type'] = 'dp';
                            } else {
                                $updateData['payment_status'] = 'partial';
                            }

                            $booking->update($updateData);

                            return $updateData;
                        });

                        if ($updateData === null) {
                            Log::info('Duplicate Midtrans payment event ignored', [
                                'order_id' => $orderId,
                                'booking_id' => $bookingId,
                            ]);
                            break;
                        }

                        Log::info('Booking payment updated', [
                            'booking_id' => $bookingId,
                            'payment_status' => $updateData['payment_status'],
                            'paid_amount' => $updateData['paid_amount'],
                            'remaining_amount' => $updateData['remaining_amount'],
                        ]);
                        break;

                    case 'pending':
                        $booking->update([
                            'payment_status' => 'pending',
                        ]);
                        Log::info('Booking payment marked as PENDING', [
                            'booking_id' => $bookingId,
                        ]);
                        break;

                    case 'cancel':
                    case 'expire':
                    case 'deny':
                    case 'failure':
                        $booking->update([
                            'payment_status' => 'failed',
                        ]);
                        Log::info('Booking payment marked as FAILED', [
                            'booking_id' => $bookingId,
                            'status' => $transactionStatus,
                        ]);
                        break;

                    default:
                        Log::warning('Unknown transaction status', [
                            'order_id' => $orderId,
                            'status' => $transactionStatus,
                        ]);
                }
            } else {
                Log::warning('Order ID format not recognized', ['order_id' => $orderId]);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Webhook handler exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
