<?php

namespace App\Services;

/**
 * Akun pembayaran yang sudah diputuskan untuk satu transaksi.
 *
 * `routingSource` disimpan apa adanya di `payments.routing_source` dan tidak
 * pernah dihitung ulang saat webhook datang.
 */
final class ResolvedPaymentAccount
{
    public const SOURCE_CLINIC = 'clinic_account';
    public const SOURCE_CENTRAL_FALLBACK = 'central_fallback';
    public const SOURCE_LEGACY_CENTRAL = 'legacy_central';

    public function __construct(
        public readonly string $routingSource,
        public readonly string $environment,
        public readonly ?int $paymentAccountId,
        public readonly ?int $credentialVersionId,
        public readonly string $merchantId,
        public readonly ?string $clientKey,
        public readonly string $serverKey,
        public readonly string $snapUrl,
        public readonly string $apiUrl,
        public readonly string $label,
    ) {}

    public static function endpointsFor(string $environment): array
    {
        return $environment === 'production'
            ? [
                'snap' => 'https://app.midtrans.com/snap/v1/transactions',
                'api' => 'https://api.midtrans.com/v2',
            ]
            : [
                'snap' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
                'api' => 'https://api.sandbox.midtrans.com/v2',
            ];
    }

    public function isClinicAccount(): bool
    {
        return $this->routingSource === self::SOURCE_CLINIC;
    }

    /**
     * Data aman untuk dikirim ke aplikasi/UI. Server Key tidak pernah ikut.
     */
    public function toClientArray(): array
    {
        return [
            'routing_source' => $this->routingSource,
            'environment' => $this->environment,
            'merchant_id' => $this->merchantId,
            'client_key' => $this->clientKey,
            'merchant_label' => $this->label,
        ];
    }

    /**
     * Data aman untuk log/audit.
     */
    public function toLogContext(): array
    {
        return [
            'routing_source' => $this->routingSource,
            'environment' => $this->environment,
            'payment_account_id' => $this->paymentAccountId,
            'credential_version_id' => $this->credentialVersionId,
            'merchant_id' => $this->merchantId,
        ];
    }
}
