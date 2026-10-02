<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Services\MidtransService;
use App\Services\PaymentStatusService;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans
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

    public function preflight()
    {
        try {
            $mode = config('services.midtrans.is_production') ? 'production' : 'sandbox';
            $checks = [
                'server_key' => [
                    'status' => !empty($this->midtrans->getServerKey()) ? 'ok' : 'failed',
                    'message' => !empty($this->midtrans->getServerKey()) ? 'Server key configured' : 'MIDTRANS_SERVER_KEY is missing',
                ],
                'snap_url' => [
                    'status' => !empty($this->midtrans->getSnapUrl()) ? 'ok' : 'failed',
                    'message' => $this->midtrans->getSnapUrl() ?: 'Snap URL is missing',
                ],
                'api_url' => [
                    'status' => !empty($this->midtrans->getApiUrl()) ? 'ok' : 'failed',
                    'message' => $this->midtrans->getApiUrl() ?: 'API URL is missing',
                ],
                'mode' => [
                    'status' => 'ok',
                    'message' => $mode,
                ],
            ];

            if (!$this->midtrans->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment setup is incomplete. Check Midtrans environment values.',
                    'data' => [
                        'ready' => false,
                        'mode' => $mode,
                        'checks' => $checks,
                    ],
                ], 500);
            }

            $probe = Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->midtrans->getServerKey() . ':'),
                'Accept' => 'application/json',
            ])->get($this->midtrans->getApiUrl() . '/PETHEAL-PREFLIGHT-CHECK/status');

            $midtransReachable = in_array($probe->status(), [200, 404], true);
            $checks['midtrans_account'] = [
                'status' => $midtransReachable ? 'ok' : 'failed',
                'message' => $midtransReachable
                    ? 'Midtrans API is reachable with the configured server key'
                    : 'Midtrans rejected the configured server key or endpoint',
                'status_code' => $probe->status(),
            ];

            return response()->json([
                'success' => $midtransReachable,
                'message' => $midtransReachable ? 'Payment setup ready' : 'Payment setup failed. Check Midtrans credentials.',
                'data' => [
                    'ready' => $midtransReachable,
                    'mode' => $mode,
                    'checks' => $checks,
                ],
            ], $midtransReachable ? 200 : 502);
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
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$this->midtrans->isConfigured()) {
                Log::error('Midtrans configuration is missing', [
                    'server_key_set' => !empty($this->midtrans->getServerKey()),
                    'snap_url' => $this->midtrans->getSnapUrl(),
                    'api_url' => $this->midtrans->getApiUrl(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Midtrans configuration is incomplete. Please set MIDTRANS_SERVER_KEY and MIDTRANS_IS_PRODUCTION correctly.',
                ], 500);
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

            // Prepare request payload with callbacks
            $snapPayload = $validated;

            // Add callback URL if not present
            if (!isset($snapPayload['callbacks'])) {
                $snapPayload['callbacks'] = [
                    // Use HTTP URL that WebView can intercept and redirect to custom scheme
                    // The app will handle this redirect in the WebView's URL loading
                    'finish' => 'https://app.sandbox.midtrans.com/payment/finish',
                ];
            }

            Log::info('Calling Midtrans Snap API', [
                'url' => $this->midtrans->getSnapUrl(),
                'order_id' => $snapPayload['transaction_details']['order_id'],
            ]);

            // Call Midtrans Snap API
            $result = $this->midtrans->createSnapToken($snapPayload);

            if ($result['success']) {
                $data = $result['data'];

                Log::info('Snap token created successfully', [
                    'order_id' => $validated['transaction_details']['order_id'],
                    'amount' => $validated['transaction_details']['gross_amount'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Snap token created successfully',
                    'data' => $data,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create snap token: ' . $result['message'],
                'detail' => $result['detail'] ?? null,
            ], $result['status']);
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
                    'message' => 'User not authenticated'
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

            $result = $this->midtrans->getTransactionStatus($orderId);

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
                'status_code' => $result['status'],
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

            $result = $this->midtrans->getTransactionStatus($orderId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'status_code' => $result['status'],
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
                'message' => ($updateResult['duplicate'] ?? false)
                    ? 'Payment status already synchronized'
                    : 'Payment status synchronized successfully',
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

            if (!$this->midtrans->isConfigured()) {
                Log::error('Midtrans configuration is missing for remaining payment', [
                    'server_key_set' => !empty($this->midtrans->getServerKey()),
                    'snap_url' => $this->midtrans->getSnapUrl(),
                    'api_url' => $this->midtrans->getApiUrl(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Midtrans configuration is incomplete. Please set MIDTRANS_SERVER_KEY and MIDTRANS_IS_PRODUCTION correctly.',
                ], 500);
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

            // Calculate remaining amount
            $remainingAmount = $booking->total_amount - $booking->paid_amount;

            if ($remainingAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No remaining amount to pay'
                ], 400);
            }

            // Get pet name safely
            $petName = $booking->pet ? $booking->pet->name : 'Pet';

            Log::info('Creating snap token for remaining payment', [
                'user_id' => $user->id,
                'booking_id' => $bookingId,
                'remaining_amount' => $remainingAmount,
                'total_amount' => $booking->total_amount,
                'paid_amount' => $booking->paid_amount,
            ]);

            // Generate order ID for remaining payment
            $orderId = "BOOKING-{$bookingId}-REMAINING-" . time();

            // Build Snap payload
            $snapPayload = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int) $remainingAmount,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => "REMAINING-{$bookingId}",
                        'price' => (int) $remainingAmount,
                        'quantity' => 1,
                        'name' => "Remaining Payment - {$petName}",
                    ],
                ],
                'callbacks' => [
                    'finish' => 'https://app.sandbox.midtrans.com/payment/finish',
                ],
                'enabled_payments' => [
                    'credit_card',
                    'gopay',
                    'shopeepay',
                    'bca_va',
                    'bni_va',
                    'bri_va',
                    'mandiri_va',
                    'permata_va',
                    'cimb_va',
                    'qris',
                ],
                'credit_card' => [
                    'secure' => true,
                ],
            ];

            Log::info('Calling Midtrans Snap API for remaining payment', [
                'url' => $this->midtrans->getSnapUrl(),
                'order_id' => $orderId,
                'amount' => $remainingAmount,
            ]);

            // Call Midtrans Snap API
            $result = $this->midtrans->createSnapToken($snapPayload);

            if ($result['success']) {
                $data = $result['data'];

                Log::info('Snap token created successfully for remaining payment', [
                    'booking_id' => $bookingId,
                    'order_id' => $orderId,
                    'amount' => $remainingAmount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Snap token created successfully for remaining payment',
                    'data' => [
                        'token' => $data['token'],
                        'redirect_url' => $data['redirect_url'],
                        'transaction_id' => $data['transaction_id'] ?? null,
                        'order_id' => $orderId,
                        'booking' => [
                            'id' => $booking->id,
                            'total_amount' => $booking->total_amount,
                            'paid_amount' => $booking->paid_amount,
                            'remaining_amount' => $remainingAmount,
                            'payment_status' => $booking->payment_status,
                        ],
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create snap token: ' . $result['message'],
                'detail' => $result['detail'] ?? null,
            ], $result['status']);
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
                    'message' => 'User not authenticated'
                ], 401);
            }

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
