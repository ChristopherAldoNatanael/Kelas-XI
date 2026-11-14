<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Services\MidtransPartnerGateway;
use App\Services\MidtransService;
use App\Services\PaymentService;
use App\Services\PaymentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Satu endpoint notifikasi Midtrans untuk seluruh domain.
 *
 * - BOOKING-* / MEDREC-* : perilaku lama dipertahankan (booking & biaya
 *   tambahan rekam medis).
 * - SHOP-* : Payment Service terpusat, termasuk validasi merchant/partner.
 *
 * Signature yang dipakai mengikuti jenis notifikasi:
 * - Merchant : SHA512(order_id + status_code + gross_amount + MerchantServerKey)
 * - Partner  : SHA512(order_id + status_code + gross_amount + PartnerServerKey)
 * Keduanya adalah skema resmi Midtrans; bukan signature karangan.
 */
class MidtransWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly MidtransPartnerGateway $partnerGateway,
        private readonly PaymentService $payments
    ) {}

    public function handle(Request $request, PaymentStatusService $paymentStatusService): JsonResponse
    {
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
            'has_partner_id' => !empty($payload['partner_id']),
        ]);

        if (!$orderId) {
            return response()->json(['error' => 'Missing order_id'], 400);
        }

        $isPartnerNotification = !empty($payload['partner_id']);
        $signatureValid = $this->verifySignature($payload, (string) $orderId, (string) $statusCode, (string) $grossAmount, $signatureKey, $isPartnerNotification);

        $payment = Payment::where('provider_order_id', $orderId)->first();
        $notification = $this->payments->logNotification(
            $payload,
            $signatureValid,
            $payment,
            $signatureValid ? null : 'Signature tidak valid'
        );

        if (!$signatureValid) {
            Log::warning('Invalid webhook signature', [
                'order_id' => $orderId,
                'partner_notification' => $isPartnerNotification,
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        try {
            if (str_starts_with((string) $orderId, 'SHOP-')) {
                return $this->handlePetShop($notification, $payment, $payload);
            }

            if (preg_match('/^MEDREC-(\d+)-/', $orderId, $matches)) {
                return $this->handleMedicalRecord($notification, $matches[1], $orderId, $transactionStatus, $paymentType, $grossAmount, $payload, $paymentStatusService);
            }

            if (preg_match('/^BOOKING-(\d+)/', $orderId, $matches)) {
                return $this->handleBooking($notification, $matches[1], $orderId, $transactionStatus, $paymentType, $grossAmount, $payload, $paymentStatusService);
            }

            Log::warning('Order ID format not recognized', ['order_id' => $orderId]);
            $notification->forceFill(['processing_note' => 'Order ID tidak dikenal'])->save();

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('Webhook handler exception', [
                'message' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function handlePetShop(PaymentNotification $notification, ?Payment $payment, array $payload): JsonResponse
    {
        if (!$payment) {
            $notification->forceFill(['processing_note' => 'Payment tidak ditemukan untuk order ini'])->save();
            Log::warning('Pet shop payment not found for webhook', ['order_id' => $payload['order_id']]);

            // Diterima agar provider tidak mengirim ulang tanpa henti; baris
            // notifikasi tetap tersimpan untuk peninjauan manual.
            return response()->json(['status' => 'ok']);
        }

        if (isset($payload['merchant_id']) && filled($payment->merchant_id)
            && (string) $payload['merchant_id'] !== (string) $payment->merchant_id) {
            $notification->forceFill(['processing_note' => 'Merchant ID tidak cocok'])->save();

            return response()->json(['error' => 'Merchant mismatch'], 422);
        }

        if (isset($payload['partner_id']) && filled($payment->partner_id)
            && (string) $payload['partner_id'] !== (string) $payment->partner_id) {
            $notification->forceFill(['processing_note' => 'Partner ID tidak cocok'])->save();

            return response()->json(['error' => 'Partner mismatch'], 422);
        }

        if (isset($payload['gross_amount'])
            && (int) round((float) $payload['gross_amount']) !== (int) $payment->gross_amount) {
            $notification->forceFill(['processing_note' => 'Gross amount tidak cocok'])->save();
            Log::warning('Pet shop webhook gross mismatch', [
                'payment_id' => $payment->id,
                'provider' => $payload['gross_amount'],
                'internal' => $payment->gross_amount,
            ]);

            return response()->json(['error' => 'Gross amount mismatch'], 422);
        }

        if (isset($payload['currency'])
            && strtoupper((string) $payload['currency']) !== strtoupper((string) $payment->currency)) {
            $notification->forceFill(['processing_note' => 'Currency tidak cocok'])->save();

            return response()->json(['error' => 'Currency mismatch'], 422);
        }

        $result = $this->payments->applyProviderNotification($payment, $payload, 'webhook');

        $notification->forceFill([
            'payment_id' => $payment->id,
            'processed' => true,
            'processing_note' => $result['changed'] ? 'Status diperbarui' : 'Tidak ada perubahan (idempoten)',
        ])->save();

        return response()->json(['status' => 'ok']);
    }

    private function handleMedicalRecord(
        PaymentNotification $notification,
        string $recordId,
        string $orderId,
        ?string $transactionStatus,
        ?string $paymentType,
        mixed $grossAmount,
        array $payload,
        PaymentStatusService $paymentStatusService
    ): JsonResponse {
        $record = MedicalRecord::find((int) $recordId);

        if (!$record) {
            $notification->forceFill(['processing_note' => 'Rekam medis tidak ditemukan'])->save();

            return response()->json(['error' => 'Medical record not found'], 404);
        }

        $result = $paymentStatusService->applyMedicalRecordTransactionStatus(
            $record,
            $orderId,
            (string) $transactionStatus,
            $paymentType,
            $grossAmount,
            $payload
        );

        $notification->forceFill([
            'processed' => true,
            'processing_note' => ($result['duplicate'] ?? false) ? 'Duplikat diabaikan' : 'Status rekam medis diperbarui',
        ])->save();

        return response()->json(['status' => 'ok']);
    }

    private function handleBooking(
        PaymentNotification $notification,
        string $bookingId,
        string $orderId,
        ?string $transactionStatus,
        ?string $paymentType,
        mixed $grossAmount,
        array $payload,
        PaymentStatusService $paymentStatusService
    ): JsonResponse {
        $booking = Booking::find((int) $bookingId);

        if (!$booking) {
            $notification->forceFill(['processing_note' => 'Booking tidak ditemukan'])->save();

            return response()->json(['error' => 'Booking not found'], 404);
        }

        $result = $paymentStatusService->applyTransactionStatus(
            $booking,
            $orderId,
            (string) $transactionStatus,
            $paymentType,
            $grossAmount,
            $payload
        );

        // Write-through ke payment domain: transaksi konsultasi kini punya
        // jejak yang sama tanpa mengubah alur booking lama.
        $payment = Payment::where('provider_order_id', $orderId)->first();
        if ($payment) {
            $this->payments->applyProviderNotification($payment, $payload, 'webhook');
        }

        $notification->forceFill([
            'processed' => true,
            'processing_note' => ($result['duplicate'] ?? false) ? 'Duplikat diabaikan' : 'Status booking diperbarui',
        ])->save();

        return response()->json(['status' => 'ok']);
    }

    private function verifySignature(
        array $payload,
        string $orderId,
        string $statusCode,
        string $grossAmount,
        mixed $signatureKey,
        bool $partnerNotification
    ): bool {
        if (!is_string($signatureKey) || $signatureKey === '') {
            return false;
        }

        if ($partnerNotification) {
            return $this->partnerGateway->verifyNotification($payload);
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->midtrans->getServerKey());

        return hash_equals($expected, $signatureKey);
    }
}
