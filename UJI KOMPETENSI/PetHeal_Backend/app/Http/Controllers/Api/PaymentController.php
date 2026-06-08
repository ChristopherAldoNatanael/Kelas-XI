<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;

class PaymentController extends Controller
{
    // Midtrans Configuration (loaded from .env)
    private function getSnapUrl(): string
    {
        return config('services.midtrans.snap_url');
    }

    private function getApiUrl(): string
    {
        return config('services.midtrans.api_url');
    }

    private function getServerKey(): string
    {
        return config('services.midtrans.server_key');
    }

    private function isMidtransConfigured(): bool
    {
        return !empty($this->getServerKey()) && !empty($this->getSnapUrl()) && !empty($this->getApiUrl());
    }

    public function preflight()
    {
        try {
            $mode = config('services.midtrans.is_production') ? 'production' : 'sandbox';
            $checks = [
                'server_key' => [
                    'status' => !empty($this->getServerKey()) ? 'ok' : 'failed',
                    'message' => !empty($this->getServerKey()) ? 'Server key configured' : 'MIDTRANS_SERVER_KEY is missing',
                ],
                'snap_url' => [
                    'status' => !empty($this->getSnapUrl()) ? 'ok' : 'failed',
                    'message' => $this->getSnapUrl() ?: 'Snap URL is missing',
                ],
                'api_url' => [
                    'status' => !empty($this->getApiUrl()) ? 'ok' : 'failed',
                    'message' => $this->getApiUrl() ?: 'API URL is missing',
                ],
                'mode' => [
                    'status' => 'ok',
                    'message' => $mode,
                ],
            ];

            if (!$this->isMidtransConfigured()) {
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
                'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':'),
                'Accept' => 'application/json',
            ])->get($this->getApiUrl() . '/PETHEAL-PREFLIGHT-CHECK/status');

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

            if (!$this->isMidtransConfigured()) {
                Log::error('Midtrans configuration is missing', [
                    'server_key_set' => !empty($this->getServerKey()),
                    'snap_url' => $this->getSnapUrl(),
                    'api_url' => $this->getApiUrl(),
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

            preg_match('/^BOOKING-(\d+)-\d+$/', $validated['transaction_details']['order_id'], $matches);
            $bookingId = (int) ($matches[1] ?? 0);
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
                'url' => $this->getSnapUrl(),
                'order_id' => $snapPayload['transaction_details']['order_id'],
            ]);

            // Call Midtrans Snap API
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->getSnapUrl(), $snapPayload);

            if ($response->successful()) {
                $data = $response->json();

                // Validate response data
                if (!isset($data['token']) || !isset($data['redirect_url'])) {
                    Log::error('Midtrans Snap API returned invalid response', [
                        'response' => $data,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid response from payment gateway',
                    ], 502);
                }

                Log::info('Snap token created successfully', [
                    'order_id' => $validated['transaction_details']['order_id'],
                    'amount' => $validated['transaction_details']['gross_amount'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Snap token created successfully',
                    'data' => [
                        'token' => $data['token'],
                        'redirect_url' => $data['redirect_url'],
                        'transaction_id' => $data['transaction_id'] ?? null,
                    ]
                ]);
            }

            $errorBody = $response->body();
            $errorData = json_decode($errorBody, true);

            // Handle different error scenarios
            $errorMessage = match ($response->status()) {
                400 => 'Invalid request data sent to payment gateway',
                401 => 'Payment gateway authentication failed',
                404 => 'Payment gateway endpoint not found. Please check configuration.',
                500 => 'Payment gateway server error',
                502 => 'Payment gateway bad response',
                default => $errorData['message'] ?? 'Unknown error from payment gateway',
            };

            Log::error('Midtrans Snap API error', [
                'status' => $response->status(),
                'body' => $errorBody,
                'order_id' => $validated['transaction_details']['order_id'] ?? null,
                'error_message' => $errorMessage,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create snap token: ' . $errorMessage,
                'detail' => $errorData['message'] ?? null,
            ], $response->status() === 404 ? 502 : $response->status());
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

            if (!preg_match('/^BOOKING-(\d+)(?:-|$)/', $orderId, $matches)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order ID',
                ], 422);
            }

            $booking = $user->bookings()->find((int) $matches[1]);
            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found',
                ], 404);
            }

            Log::info('Checking transaction status', [
                'user_id' => $user->id,
                'order_id' => $orderId,
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':'),
                'Accept' => 'application/json',
            ])->get($this->getApiUrl() . '/' . $orderId . '/status');

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Transaction status retrieved', [
                    'order_id' => $orderId,
                    'status' => $data['transaction_status'] ?? 'unknown',
                    'payment_type' => $data['payment_type'] ?? null,
                ]);

                return response()->json($data);
            }

            $errorBody = $response->body();
            Log::error('Midtrans Status API error', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $errorBody,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get transaction status',
                'status_code' => $response->status(),
            ], $response->status() === 404 ? 404 : 502);
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

            if (!$this->isMidtransConfigured()) {
                Log::error('Midtrans configuration is missing for remaining payment', [
                    'server_key_set' => !empty($this->getServerKey()),
                    'snap_url' => $this->getSnapUrl(),
                    'api_url' => $this->getApiUrl(),
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
                'url' => $this->getSnapUrl(),
                'order_id' => $orderId,
                'amount' => $remainingAmount,
            ]);

            // Call Midtrans Snap API
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->getSnapUrl(), $snapPayload);

            if ($response->successful()) {
                $data = $response->json();

                if (!isset($data['token']) || !isset($data['redirect_url'])) {
                    Log::error('Midtrans Snap API returned invalid response for remaining payment', [
                        'response' => $data,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid response from payment gateway',
                    ], 502);
                }

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

            $errorBody = $response->body();
            $errorData = json_decode($errorBody, true);

            $errorMessage = match ($response->status()) {
                400 => 'Invalid request data sent to payment gateway',
                401 => 'Payment gateway authentication failed',
                404 => 'Payment gateway endpoint not found. Please check configuration.',
                500 => 'Payment gateway server error',
                502 => 'Payment gateway bad response',
                default => $errorData['message'] ?? 'Unknown error from payment gateway',
            };

            Log::error('Midtrans Snap API error for remaining payment', [
                'status' => $response->status(),
                'body' => $errorBody,
                'booking_id' => $bookingId,
                'error_message' => $errorMessage,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create snap token: ' . $errorMessage,
                'detail' => $errorData['message'] ?? null,
            ], $response->status() === 404 ? 502 : $response->status());
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
