<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

            Log::info('Midtrans webhook received', $payload);

            $orderId = $payload['order_id'] ?? null;
            $transactionStatus = $payload['transaction_status'] ?? null;
            $statusCode = $payload['status_code'] ?? null;
            $grossAmount = $payload['gross_amount'] ?? null;
            $signatureKey = $payload['signature_key'] ?? null;
            $paymentType = $payload['payment_type'] ?? null;

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
                    'received' => $signatureKey,
                    'expected' => $expectedSignature,
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
                        // Payment successful
                        $currentPaidAmount = $booking->paid_amount + $grossAmount;
                        $remainingAmount = $booking->total_amount - $currentPaidAmount;

                        // Ensure remaining amount doesn't go below 0
                        if ($remainingAmount < 0) {
                            $remainingAmount = 0;
                        }

                        $updateData = [
                            'paid_amount' => $currentPaidAmount,
                            'remaining_amount' => $remainingAmount,
                        ];

                        // Determine payment status
                        if ($remainingAmount == 0) {
                            // Fully paid
                            $updateData['payment_status'] = 'paid';

                            // If this was DP booking and now fully paid, update payment type
                            if ($booking->payment_type === 'dp' && $booking->remaining_amount > 0) {
                                Log::info('DP booking now fully paid', [
                                    'booking_id' => $bookingId,
                                    'first_payment' => $booking->paid_amount,
                                    'second_payment' => $grossAmount,
                                ]);
                            }
                        } elseif ($booking->payment_type === 'dp' && $booking->paid_amount == 0) {
                            // First DP payment
                            $updateData['payment_status'] = 'dp_paid';
                            $updateData['payment_type'] = 'dp';
                        } else {
                            // Partial payment (shouldn't happen often)
                            $updateData['payment_status'] = 'partial';
                        }

                        $booking->update($updateData);

                        Log::info('Booking payment updated', [
                            'booking_id' => $bookingId,
                            'payment_status' => $updateData['payment_status'],
                            'paid_amount' => $currentPaidAmount,
                            'remaining_amount' => $remainingAmount,
                            'total_amount' => $booking->total_amount,
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
