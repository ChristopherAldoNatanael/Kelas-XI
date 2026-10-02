<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function getSnapUrl(): string
    {
        return config('services.midtrans.snap_url');
    }

    public function getApiUrl(): string
    {
        return config('services.midtrans.api_url');
    }

    public function getServerKey(): string
    {
        return config('services.midtrans.server_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->getServerKey())
            && !empty($this->getSnapUrl())
            && !empty($this->getApiUrl());
    }

    /**
     * Create a Snap token via Midtrans Snap API.
     *
     * @return array{success: bool, data?: array, message?: string, status?: int}
     */
    public function createSnapToken(array $payload): array
    {
        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->getSnapUrl(), $payload);

        if ($response->successful()) {
            $data = $response->json();

            if (!isset($data['token']) || !isset($data['redirect_url'])) {
                Log::error('Midtrans Snap API returned invalid response', ['response' => $data]);
                return ['success' => false, 'message' => 'Invalid response from payment gateway', 'status' => 502];
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

        Log::error('Midtrans Snap API error', [
            'status' => $response->status(),
            'body' => $errorBody,
            'error_message' => $errorMessage,
        ]);

        return [
            'success' => false,
            'message' => $errorMessage,
            'detail' => $errorData['message'] ?? null,
            'status' => $response->status() === 404 ? 502 : $response->status(),
        ];
    }

    /**
     * Get transaction status from Midtrans Transaction API.
     *
     * @return array{success: bool, data?: array, message?: string, status?: int}
     */
    public function getTransactionStatus(string $orderId): array
    {
        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->getServerKey() . ':'),
            'Accept' => 'application/json',
        ])->get($this->getApiUrl() . '/' . $orderId . '/status');

        if ($response->successful()) {
            return ['success' => true, 'data' => $response->json()];
        }

        Log::error('Midtrans Status API error', [
            'order_id' => $orderId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Failed to get transaction status',
            'status' => $response->status() === 404 ? 404 : 502,
        ];
    }
}