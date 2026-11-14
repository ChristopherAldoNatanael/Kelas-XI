<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\MidtransAccountGateway;
use App\Services\PaymentAccountResolver;
use App\Services\PaymentGatewayException;
use App\Services\PaymentRoutingException;
use App\Services\PaymentService;
use App\Services\PaymentStatusService;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentService $payments,
        private readonly PaymentAccountResolver $resolver,
        private readonly MidtransAccountGateway $gateway
    ) {}

    private function parsePaymentOrderId(string $orderId): ?array
    {
        if (preg_match('/^BOOKING-(\d+)-(\d+)$/', $orderId, $matches)) {
            return [
                'prefix' => 'BOOKING',
                'target_id' => (int) $matches[1],
                'timestamp' => $matches[2],
                'is_remaining_payment' => false,
            ];
        }

        if (preg_match('/^BOOKING-(\d+)-REMAINING-(\d+)$/', $orderId, $matches)) {
            return [
                'prefix' => 'BOOKING',
                'target_id' => (int) $matches[1],
                'timestamp' => $matches[2],
                'is_remaining_payment' => true,
            ];
        }

        if (preg_match('/^MEDREC-(\d+)-(\d+)$/', $orderId, $matches)) {
            return [
                'prefix' => 'MEDREC',
                'target_id' => (int) $matches[1],
                'timestamp' => $matches[2],
                'is_remaining_payment' => false,
            ];
        }

        return null;
    }

    /**
     * Kesiapan pembayaran online untuk pengguna yang sedang login.
     *
     * Sejak routing multi-klinik, kredensial pusat BUKAN lagi syarat utama:
     * preflight harus mengikuti akun yang akan dipakai transaksi (klinik milik
     * pengguna, atau fallback pusat bila klinik belum pernah konfigurasi).
     * Tidak ada panggilan jaringan ke Midtrans di sini supaya preflight tidak
     * gagal hanya karena key pusat belum diisi. Kegagalan provider tetap
     * dilaporkan saat Snap token dibuat.
     */
    public function preflight()
    {
        try {
            $mode = $this->resolver->environment();
            $clinic = Auth::user()?->clinic;
            $availability = $this->resolver->availability($clinic);

            $checks = [
                'mode' => [
                    'status' => 'ok',
                    'message' => $mode,
                ],
                'clinic' => [
                    'status' => $clinic !== null ? 'ok' : 'skipped',
                    'message' => $clinic?->name ?? 'No clinic context',
                ],
                'routing' => [
                    'status' => $availability['available'] ? 'ok' : 'failed',
                    'message' => $availability['available']
                        ? ($availability['merchant_label'] ?? 'Midtrans account ready')
                        : ($availability['reason'] ?? 'No usable Midtrans account'),
                ],
            ];

            if (!$availability['available']) {
                return response()->json([
                    'success' => false,
                    'message' => __('payments.routing_unavailable'),
                    'code' => 'online_payment_unavailable',
                    'reason' => $availability['reason'],
                    'data' => [
                        'ready' => false,
                        'mode' => $mode,
                        'checks' => $checks,
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment setup ready',
                'data' => [
                    'ready' => true,
                    'mode' => $mode,
                    'source' => $availability['source'],
                    'checks' => $checks,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception in payment preflight', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify payment setup: ' . $e->getMessage(),
                'data' => [
                    'ready' => false,
                    'mode' => config('services.midtrans.is_production') ? 'production' : 'sandbox',
                    'checks' => [],
                ],
            ], 500);
        }
    }

    /**
     * Create a Midtrans Snap token for a booking payment.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSnapToken(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                Log::warning('Unauthenticated attempt to create snap token');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Validate request
            $validated = $request->validate([
                'transaction_details' => 'required|array',
                'transaction_details.order_id' => 'required|string|regex:/^BOOKING-\d+-\d+$/',
                'transaction_details.gross_amount' => 'required|numeric|min:1',
                'customer_details' => 'nullable|array',
                'item_details' => 'nullable|array',
                'enabled_payments' => 'nullable|array',
                'credit_card' => 'nullable|array',
            ]);

            $parsedOrder = $this->parsePaymentOrderId($validated['transaction_details']['order_id']);
            if (!$parsedOrder || $parsedOrder['prefix'] !== 'BOOKING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid booking order ID',
                ], 422);
            }

            $bookingId = $parsedOrder['target_id'];
            $booking = $user->bookings()->find($bookingId);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found',
                ], 404);
            }

            if ($booking->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking is already paid',
                ], 400);
            }

            $expectedAmount = $booking->payment_type === 'dp'
                ? (int) round((float) $booking->dp_amount)
                : (int) round((float) $booking->total_amount);
            $requestedAmount = (int) round((float) $validated['transaction_details']['gross_amount']);

            if ($requestedAmount !== $expectedAmount) {
                Log::warning('Rejected snap token amount mismatch', [
                    'user_id' => $user->id,
                    'booking_id' => $booking->id,
                    'requested_amount' => $requestedAmount,
                    'expected_amount' => $expectedAmount,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount does not match booking amount',
                ], 422);
            }

            Log::info('Creating snap token', [
                'user_id' => $user->id,
                'order_id' => $validated['transaction_details']['order_id'],
                'gross_amount' => $validated['transaction_details']['gross_amount'],
            ]);

            // Nominal, order id, dan akun merchant diputuskan server.
            $kind = $booking->payment_type === 'dp' ? 'dp' : 'full';
            $payment = $this->payments->createBookingPayment(
                $booking,
                $kind,
                (string) $validated['transaction_details']['order_id']
            );

            Log::info('Booking payment created with routed account', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'routing_source' => $payment->routing_source,
                'environment' => $payment->environment,
                'amount' => $payment->gross_amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Snap token created successfully',
                'data' => $this->paymentResponseData($payment),
            ]);
        } catch (PaymentRoutingException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'online_payment_unavailable',
                'reason' => $e->reason,
            ], $e->httpStatus);
        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create snap token: ' . $e->getMessage(),
            ], 502);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error in createSnapToken', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Throwable in createSnapToken', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Exception in createSnapToken', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction status from Midtrans.
     *
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionStatus(string $orderId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $parsedOrder = $this->parsePaymentOrderId($orderId);
            if (!$parsedOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order ID',
                ], 422);
            }

            if ($parsedOrder['prefix'] === 'BOOKING') {
                $owned = $user->bookings()->find($parsedOrder['target_id']);
            } else {
                $owned = MedicalRecord::whereKey($parsedOrder['target_id'])
                    ->whereHas('booking', fn ($query) => $query->where('user_id', $user->id))
                    ->first();
            }

            if (!$owned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment target not found',
                ], 404);
            }

            Log::info('Checking transaction status', [
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);

            $result = $this->fetchProviderStatus($orderId);

            if ($result['success']) {
                $data = $result['data'];

                Log::info('Transaction status retrieved', [
                    'order_id' => $orderId,
                    'status' => $data['transaction_status'] ?? 'unknown',
                    'payment_type' => $data['payment_type'] ?? null,
                ]);

                return response()->json($data);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'errors' => ['upstream_status' => $result['status']],
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Exception in getTransactionStatus', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Synchronize a booking payment by re-reading the latest Midtrans status
     * and applying it to the local booking/payment tables.
     */
    public function syncPaymentStatus(Request $request, PaymentStatusService $paymentStatusService)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string|max:100',
            ]);

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $orderId = $validated['order_id'];
            Log::info('Payment sync requested', [
                'order_id' => $orderId,
                'user_id' => $request->user()?->id,
            ]);

            $parsedOrder = $this->parsePaymentOrderId($orderId);
            if (!$parsedOrder) {
                Log::warning('Payment sync invalid order id format', [
                    'order_id' => $orderId,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment order id format',
                ], 422);
            }

            Log::info(
                $parsedOrder['prefix'] === 'BOOKING'
                    ? 'Payment sync parsed booking order'
                    : 'Payment sync parsed medical record order',
                [
                    'order_id' => $orderId,
                    $parsedOrder['prefix'] === 'BOOKING' ? 'booking_id' : 'medical_record_id' => $parsedOrder['target_id'],
                    'timestamp' => $parsedOrder['timestamp'],
                    'is_remaining_payment' => $parsedOrder['is_remaining_payment'],
                ]
            );

            Log::info('Midtrans status sync requested', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'payment_target_type' => $parsedOrder['prefix'],
                'payment_target_id' => $parsedOrder['target_id'],
                'timestamp' => $parsedOrder['timestamp'],
                'is_remaining_payment' => $parsedOrder['is_remaining_payment'],
            ]);

            if ($parsedOrder['prefix'] === 'BOOKING') {
                $paymentTarget = $user->bookings()->find($parsedOrder['target_id']);
            } else {
                $paymentTarget = MedicalRecord::whereKey($parsedOrder['target_id'])
                    ->whereHas('booking', fn ($query) => $query->where('user_id', $user->id))
                    ->first();
            }

            if (!$paymentTarget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment target not found',
                ], 404);
            }

            $result = $this->fetchProviderStatus($orderId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => ['upstream_status' => $result['status']],
                ], $result['status']);
            }

            $data = $result['data'];
            $transactionStatus = (string) ($data['transaction_status'] ?? 'unknown');
            $fraudStatus = $data['fraud_status'] ?? null;
            $paymentType = $data['payment_type'] ?? null;
            $grossAmount = $data['gross_amount'] ?? 0;

            Log::info('Payment sync Midtrans status', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
            ]);

            // Payment row (booking/MEDREC) diperbarui lewat PaymentService
            // supaya status, jejak history, dan domain payable selalu sinkron.
            // Transaksi legacy tanpa Payment row tetap memakai jalur lama.
            $paymentRow = Payment::where('provider_order_id', $orderId)->first();
            $paymentRowChanged = false;

            if ($paymentRow !== null) {
                $paymentRowChanged = (bool) $this->payments
                    ->applyProviderNotification($paymentRow, $data, 'sync')['changed'];
            }

            if ($paymentTarget instanceof Booking) {
                $updateResult = $paymentStatusService->applyTransactionStatus(
                    $paymentTarget,
                    $orderId,
                    $transactionStatus,
                    $paymentType,
                    $grossAmount,
                    $data
                );

                $updatedBooking = $updateResult['booking'];

                $responseData = [
                    'id' => $updatedBooking->id,
                    'payment_type' => $updatedBooking->payment_type,
                    'payment_status' => $updatedBooking->payment_status,
                    'total_amount' => $updatedBooking->total_amount,
                    'dp_amount' => $updatedBooking->dp_amount,
                    'paid_amount' => $updatedBooking->paid_amount,
                    'remaining_amount' => $updatedBooking->remaining_amount ?? ($updatedBooking->total_amount - $updatedBooking->paid_amount),
                    'payment_date' => $updatedBooking->payment_date?->toDateTimeString(),
                    'transaction_status' => $transactionStatus,
                ];

                Log::info('Payment sync database updated', [
                    'order_id' => $orderId,
                    'payment_status' => $updatedBooking->payment_status,
                    'booking_payment_status' => $updatedBooking->payment_status,
                ]);
            } else {
                $updateResult = $paymentStatusService->applyMedicalRecordTransactionStatus(
                    $paymentTarget,
                    $orderId,
                    $transactionStatus,
                    $paymentType,
                    $grossAmount,
                    $data
                );

                $updatedRecord = $updateResult['medical_record'];
                $responseData = [
                    'id' => $updatedRecord->id,
                    'medical_record_id' => $updatedRecord->id,
                    'payment_status' => $updatedRecord->extra_payment_status,
                    'extra_payment_status' => $updatedRecord->extra_payment_status,
                    'extra_payment_amount' => $updatedRecord->extra_payment_amount,
                    'extra_payment_paid_amount' => $updatedRecord->extra_payment_paid_amount,
                    'payment_date' => $updatedRecord->extra_payment_date?->toDateTimeString(),
                    'transaction_status' => $transactionStatus,
                ];

                Log::info('Payment sync database updated', [
                    'order_id' => $orderId,
                    'payment_status' => $updatedRecord->extra_payment_status,
                    'medical_record_extra_payment_status' => $updatedRecord->extra_payment_status,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => ($paymentRowChanged || !($updateResult['duplicate'] ?? false))
                    ? 'Payment status synchronized successfully'
                    : 'Payment status already synchronized',
                'data' => $responseData,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Exception in syncPaymentStatus', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a Snap token for remaining payment (after DP).
     *
     * @param Request $request
     * @param int $bookingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function createRemainingPaymentSnapToken(Request $request, int $bookingId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Find booking
            $booking = $user->bookings()->find($bookingId);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // Verify this is a DP booking with remaining amount
            if ($booking->payment_type !== 'dp') {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking is not a DP payment'
                ], 400);
            }

            if ($booking->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking is already fully paid'
                ], 400);
            }

            // PHASE 3 (B5): remaining payment only makes sense after money
            // moved. A fresh DP booking (paid=0, stored remaining=DP leg)
            // must go through the DP flow first — otherwise this endpoint
            // would mint a token for the FULL total. Cancelled bookings are
            // never payable here.
            if (in_array($booking->status, ['cancelled', 'completed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remaining payment is not available for this booking'
                ], 400);
            }

            if ((float) $booking->paid_amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Complete the first payment before paying the remainder'
                ], 400);
            }

            // Calculate remaining amount from the stored column (kept
            // authoritative by every payment path) instead of recomputing.
            $remainingAmount = max(0, (float) ($booking->remaining_amount ?? 0));

            if ($remainingAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining amount to pay'
                ], 400);
            }

            Log::info('Creating snap token for remaining payment', [
                'user_id' => $user->id,
                'booking_id' => $bookingId,
                'remaining_amount' => $remainingAmount,
                'total_amount' => $booking->total_amount,
                'paid_amount' => $booking->paid_amount,
            ]);

            // Pelunasan mengikuti route pembayaran pertama booking ini.
            $payment = $this->payments->createBookingPayment($booking, 'remaining');
            $fresh = $booking->fresh();

            Log::info('Remaining payment created with routed account', [
                'booking_id' => $bookingId,
                'payment_id' => $payment->id,
                'routing_source' => $payment->routing_source,
                'amount' => $payment->gross_amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Snap token created successfully for remaining payment',
                'data' => $this->paymentResponseData($payment) + [
                    'booking' => [
                        'id' => $fresh->id,
                        'total_amount' => $fresh->total_amount,
                        'paid_amount' => $fresh->paid_amount,
                        'remaining_amount' => $fresh->remaining_amount,
                        'payment_status' => $fresh->payment_status,
                    ],
                ],
            ]);
        } catch (PaymentRoutingException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'online_payment_unavailable',
                'reason' => $e->reason,
            ], $e->httpStatus);
        } catch (PaymentGatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create snap token: ' . $e->getMessage(),
            ], 502);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Exception in createRemainingPaymentSnapToken', [
                'booking_id' => $bookingId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ambil status dari akun yang tercatat pada payment.
     * Payment legacy tanpa route tetap memakai kredensial pusat.
     */
    private function fetchProviderStatus(string $orderId): array
    {
        $payment = Payment::where('provider_order_id', $orderId)->first();

        if ($payment === null) {
            return $this->midtrans->getTransactionStatus($orderId);
        }

        try {
            $account = $this->resolver->resolveLocked($payment);
        } catch (PaymentRoutingException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => $e->httpStatus === 409 ? 404 : 503,
            ];
        }

        if ($account === null) {
            return $this->midtrans->getTransactionStatus($orderId);
        }

        return $this->gateway->getTransactionStatus($account, $orderId);
    }

    /**
     * Bentuk respons token yang kompatibel dengan Android lama
     * (`token`, `redirect_url`, `transaction_id`) + metadata routing.
     */
    private function paymentResponseData(Payment $payment): array
    {
        $clientKey = $payment->credentialVersion?->client_key
            ?: config("services.midtrans.central.{$payment->environment}.client_key");

        return [
            'token' => $payment->snap_token,
            'redirect_url' => $payment->redirect_url,
            'transaction_id' => $payment->provider_transaction_id,
            'order_id' => $payment->provider_order_id,
            'payment_id' => $payment->id,
            'gross_amount' => $payment->gross_amount,
            'client_key' => $clientKey,
            'environment' => $payment->environment,
            'routing_source' => $payment->routing_source,
        ];
    }

    /**
     * Get booking payment status.
     *
     * @param int $bookingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBookingPaymentStatus(int $bookingId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Find booking
            $booking = $user->bookings()->find($bookingId);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $booking->id,
                    'payment_type' => $booking->payment_type,
                    'payment_status' => $booking->payment_status,
                    'total_amount' => $booking->total_amount,
                    'dp_amount' => $booking->dp_amount,
                    'paid_amount' => $booking->paid_amount,
                    'remaining_amount' => $booking->remaining_amount ?? ($booking->total_amount - $booking->paid_amount),
                    'payment_date' => $booking->payment_date?->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Exception in getBookingPaymentStatus', [
                'booking_id' => $bookingId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
