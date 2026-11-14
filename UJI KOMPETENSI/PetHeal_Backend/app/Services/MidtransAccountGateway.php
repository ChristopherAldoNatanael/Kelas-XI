<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway Midtrans berbasis kredensial hasil resolver.
 *
 * Tidak menyimpan state dan tidak pernah membaca config global secara
 * langsung: setiap panggilan menerima akun yang sudah diputuskan, sehingga
 * transaksi klinik A tidak mungkin terkirim dengan Server Key klinik B.
 */
class MidtransAccountGateway
{
    public function createTransaction(ResolvedPaymentAccount $account, array $snapPayload): array
    {
        $response = Http::timeout(30)->withHeaders($this->headers($account))
            ->post($account->snapUrl, $snapPayload);

        if ($response->successful()) {
            $data = $response->json();

            if (!isset($data['token'], $data['redirect_url'])) {
                Log::error('Midtrans Snap returned invalid payload', [
                    'environment' => $account->environment,
                    'merchant_id' => $account->merchantId,
                ]);

                return ['success' => false, 'message' => 'Respons payment gateway tidak valid.', 'status' => 502];
            }

            return [
                'success' => true,
                'data' => [
                    'token' => $data['token'],
                    'redirect_url' => $data['redirect_url'],
                    'transaction_id' => $data['transaction_id'] ?? null,
                ],
            ];
        }

        $error = $response->json();
        $message = match ($response->status()) {
            400 => 'Data transaksi ditolak payment gateway.',
            401 => 'Kredensial payment gateway tidak valid.',
            404 => 'Endpoint payment gateway tidak ditemukan.',
            500, 502 => 'Payment gateway sedang bermasalah.',
            default => (string) ($error['status_message'] ?? 'Gagal membuat transaksi pembayaran.'),
        };

        Log::error('Midtrans Snap error', [
            'status' => $response->status(),
            'environment' => $account->environment,
            'merchant_id' => $account->merchantId,
            'status_message' => $error['status_message'] ?? null,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'detail' => $error['status_message'] ?? null,
            'status' => $response->status() === 404 ? 502 : $response->status(),
        ];
    }

    /**
     * @return array{success:bool, data?:array, message?:string, status?:int}
     */
    public function getTransactionStatus(ResolvedPaymentAccount $account, string $orderId): array
    {
        $response = Http::timeout(30)->withHeaders($this->headers($account))
            ->get($account->apiUrl . '/' . rawurlencode($orderId) . '/status');

        if ($response->successful()) {
            return ['success' => true, 'data' => (array) $response->json()];
        }

        return [
            'success' => false,
            'message' => 'Gagal mengambil status transaksi.',
            'status' => $response->status() === 404 ? 404 : 502,
        ];
    }

    /**
     * @return array{success:bool, refund_id?:?string, message?:string}
     */
    public function refund(ResolvedPaymentAccount $account, string $orderId, int $amount, string $reason): array
    {
        $response = Http::timeout(30)->withHeaders($this->headers($account))
            ->post($account->apiUrl . '/' . rawurlencode($orderId) . '/refund', [
                'refund_amount' => $amount,
                'reason' => $reason,
            ]);

        $body = (array) $response->json();

        if ($response->successful() && (int) ($body['status_code'] ?? 0) === 200) {
            return [
                'success' => true,
                'refund_id' => (string) ($body['refund_id'] ?? $body['transaction_id'] ?? '') ?: null,
            ];
        }

        Log::warning('Midtrans refund rejected', [
            'order_id' => $orderId,
            'status' => $response->status(),
            'environment' => $account->environment,
        ]);

        return [
            'success' => false,
            'message' => (string) ($body['status_message'] ?? 'Refund ditolak provider.'),
        ];
    }

    /**
     * Verifikasi signature resmi Midtrans dengan Server Key akun tersebut.
     */
    public function verifySignature(ResolvedPaymentAccount $account, array $payload): bool
    {
        $signature = $payload['signature_key'] ?? null;

        if (!is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash(
            'sha512',
            (string) ($payload['order_id'] ?? '')
            . (string) ($payload['status_code'] ?? '')
            . (string) ($payload['gross_amount'] ?? '')
            . $account->serverKey
        );

        return hash_equals($expected, $signature);
    }

    /**
     * Uji kredensial ke Midtrans tanpa membuat transaksi.
     *
     * Midtrans membalas 404 (transaksi probe tidak ada) bila Server Key sah,
     * dan 401/403 bila tidak. Probe memakai order id acak yang tidak pernah
     * dibuat sebagai transaksi nyata.
     *
     * @return array{valid:bool, status:int, message:string}
     */
    public function testCredentials(ResolvedPaymentAccount $account, string $probeOrderId): array
    {
        try {
            $response = Http::timeout(15)->withHeaders($this->headers($account))
                ->get($account->apiUrl . '/' . rawurlencode($probeOrderId) . '/status');
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'status' => 0,
                'message' => 'Tidak dapat menghubungi Midtrans: ' . $e->getMessage(),
            ];
        }

        $status = $response->status();

        if (in_array($status, [200, 404], true)) {
            return ['valid' => true, 'status' => $status, 'message' => 'Kredensial diterima Midtrans.'];
        }

        return [
            'valid' => false,
            'status' => $status,
            'message' => match ($status) {
                401 => 'Server Key ditolak Midtrans (401).',
                403 => 'Kredensial tidak memiliki izin (403).',
                0 => 'Midtrans tidak dapat dihubungi.',
                default => 'Midtrans menolak kredensial (HTTP ' . $status . ').',
            },
        ];
    }

    private function headers(ResolvedPaymentAccount $account): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($account->serverKey . ':'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
