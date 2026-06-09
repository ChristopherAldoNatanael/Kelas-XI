<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Services\PaymentStatusService;

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
    public function handle(Request $request, PaymentStatusService $paymentStatusService)
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

                $updateResult = $paymentStatusService->applyTransactionStatus(
                    $booking,
                    $orderId,
                    (string) $transactionStatus,
                    $paymentType,
                    $grossAmount,
                    $payload
                );

                if ($updateResult['duplicate'] ?? false) {
                    Log::info('Duplicate Midtrans payment event ignored', [
                        'order_id' => $orderId,
                        'booking_id' => $bookingId,
                    ]);
                    return response()->json(['status' => 'ok']);
                }

                $updatedBooking = $updateResult['booking'];

                if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
                    Log::info('Booking payment updated', [
                        'booking_id' => $bookingId,
                        'payment_status' => $updatedBooking->payment_status,
                        'paid_amount' => $updatedBooking->paid_amount,
                        'remaining_amount' => $updatedBooking->remaining_amount,
                        'payment_date' => $updatedBooking->payment_date?->toDateTimeString(),
                    ]);
                } elseif ($transactionStatus === 'pending') {
                    Log::info('Booking payment marked as PENDING', [
                        'booking_id' => $bookingId,
                    ]);
                } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny', 'failure'], true)) {
                    Log::info('Booking payment marked as FAILED', [
                        'booking_id' => $bookingId,
                        'status' => $transactionStatus,
                    ]);
                } else {
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
