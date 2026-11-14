<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter Midtrans Partner Solutions.
 *
 * Referensi implementasi:
 * - Transaction split: docs.midtrans.com/reference/partner-checkout-advanced-features
 * - Partner HTTP notification: docs.midtrans.com/reference/partner-http-notification
 *   Signature = SHA512(order_id + status_code + gross_amount + PartnerServerKey).
 *
 * CATATAN ONBOARDING: pemanggilan Snap untuk sub-merchant memerlukan akses
 * Partner yang sudah diaktifkan Midtrans. Header `X-Partner-Id` dipakai sesuai
 * dokumentasi partner; nilai `snap_url`, `partner_id`, dan `server_key` WAJIB
 * berasal dari dashboard Partner (bukan ditebak). Selama mode partner belum
 * aktif, gateway melaporkan dirinya tidak siap dan transaksi online tidak
 * pernah dibuat.
 */
class MidtransPartnerGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'midtrans_partner';
    }

    public function isConfigured(): bool
    {
        $config = $this->config();

        return (bool) ($config['enabled'] ?? false)
            && filled($config['partner_id'] ?? null)
            && filled($config['server_key'] ?? null)
            && filled($config['snap_url'] ?? null)
            && filled($config['api_url'] ?? null);
    }

    public function createTransaction(Payment $payment): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Midtrans Partner belum dikonfigurasi.', 'status' => 503];
        }

        if (blank($payment->merchant_id)) {
            return ['success' => false, 'message' => 'Merchant ID klinik tidak tersedia pada payment.', 'status' => 422];
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => (int) $payment->gross_amount,
            ],
            'customer_details' => array_filter([
                'first_name' => $payment->metadata['customer_name'] ?? null,
                'email' => $payment->metadata['customer_email'] ?? null,
                'phone' => $payment->metadata['customer_phone'] ?? null,
            ], fn ($value) => filled($value)),
        ];

        if (!empty($payment->split_settings)) {
            $payload['transaction_split_settings'] = $payment->split_settings;
        }

        if ($payment->expires_at && $payment->expires_at->isFuture()) {
            $payload['expiry'] = [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => max(1, (int) now()->diffInMinutes($payment->expires_at)),
            ];
        }

        $response = Http::timeout(30)
            ->withHeaders($this->headers())
            ->post($this->config()['snap_url'], $payload);

        if (!$response->successful()) {
            Log::error('Midtrans Partner Snap error', [
                'order_id' => $payment->provider_order_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $this->mapError($response->status(), $response->json()),
                'status' => $response->status() === 404 ? 502 : $response->status(),
            ];
        }

        $data = $response->json();

        if (!isset($data['token'], $data['redirect_url'])) {
            Log::error('Midtrans Partner Snap invalid response', [
                'order_id' => $payment->provider_order_id,
                'response' => $data,
            ]);

            return ['success' => false, 'message' => 'Respons payment gateway tidak valid.', 'status' => 502];
        }

        return [
            'success' => true,
            'token' => $data['token'],
            'redirect_url' => $data['redirect_url'],
            'transaction_id' => $data['transaction_id'] ?? null,
        ];
    }

    public function fetchStatus(Payment $payment): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Midtrans Partner belum dikonfigurasi.', 'status' => 503];
        }

        $response = Http::timeout(20)
            ->withHeaders($this->headers())
            ->get(rtrim($this->config()['api_url'], '/') . '/' . $payment->provider_order_id . '/status');

        if ($response->successful()) {
            return ['success' => true, 'data' => $response->json()];
        }

        return [
            'success' => false,
            'message' => 'Gagal membaca status transaksi.',
            'status' => $response->status() === 404 ? 404 : 502,
        ];
    }

    public function refund(Payment $payment, int $amount, string $reason): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Midtrans Partner belum dikonfigurasi.'];
        }

        $response = Http::timeout(30)
            ->withHeaders($this->headers())
            ->post(rtrim($this->config()['api_url'], '/') . '/' . $payment->provider_order_id . '/refund', [
                'refund_key' => $payment->payment_code . '-R' . $amount,
                'amount' => $amount,
                'reason' => $reason,
            ]);

        $data = $response->json() ?? [];

        if ($response->successful() && in_array(($data['status_code'] ?? ''), ['200', '201'], true)) {
            return [
                'success' => true,
                'refund_id' => $data['refund_id'] ?? $data['transaction_id'] ?? null,
                'status' => $data['refund_status'] ?? 'pending',
            ];
        }

        Log::warning('Midtrans Partner refund failed', [
            'order_id' => $payment->provider_order_id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return ['success' => false, 'message' => $data['status_message'] ?? 'Refund ditolak provider.'];
    }

    public function verifyNotification(array $payload): bool
    {
        $serverKey = (string) ($this->config()['server_key'] ?? '');
        $signature = $payload['signature_key'] ?? null;

        if ($serverKey === '' || !is_string($signature)) {
            return false;
        }

        $expected = hash(
            'sha512',
            ($payload['order_id'] ?? '')
            . ($payload['status_code'] ?? '')
            . ($payload['gross_amount'] ?? '')
            . $serverKey
        );

        return hash_equals($expected, $signature);
    }

    /**
     * Partner ID yang dipakai transaksi. Merchant ID penerima tetap berasal
     * dari konfigurasi klinik di backend.
     */
    public function partnerId(): ?string
    {
        return $this->config()['partner_id'] ?? null;
    }

    public function platformMerchantId(): ?string
    {
        return $this->config()['platform_merchant_id'] ?? null;
    }

    private function headers(): array
    {
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->config()['server_key'] . ':'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (filled($this->config()['partner_id'] ?? null)) {
            $headers['X-Partner-Id'] = (string) $this->config()['partner_id'];
        }

        return $headers;
    }

    private function config(): array
    {
        return (array) config('services.midtrans.partner', []);
    }

    private function mapError(int $status, ?array $body): string
    {
        return match ($status) {
            400 => $body['error_messages'][0] ?? 'Data transaksi ditolak payment gateway.',
            401 => 'Autentikasi Partner Server Key gagal.',
            404 => 'Endpoint Partner tidak ditemukan. Periksa konfigurasi.',
            429 => 'Terlalu banyak permintaan ke payment gateway.',
            500, 502, 503 => 'Payment gateway sedang bermasalah. Coba lagi nanti.',
            default => $body['status_message'] ?? 'Transaksi gagal dibuat.',
        };
    }
}
