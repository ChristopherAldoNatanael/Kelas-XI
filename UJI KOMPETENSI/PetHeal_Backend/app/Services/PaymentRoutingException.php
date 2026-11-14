<?php

namespace App\Services;

use RuntimeException;

/**
 * Pembayaran online tidak dapat dirutekan dengan aman.
 *
 * Pesan exception hanya berisi alasan teknis (bukan kredensial) dan aman
 * ditampilkan ke admin. `reason` dipakai UI untuk memilih copy yang tepat.
 */
class PaymentRoutingException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $httpStatus = 422
    ) {
        parent::__construct($message);
    }

    public static function clinicNotConfigured(): self
    {
        return new self(
            'clinic_not_configured',
            __('payments.routing_clinic_not_configured'),
            422
        );
    }

    public static function fallbackNotAllowed(): self
    {
        return new self(
            'central_fallback_disabled',
            __('payments.routing_fallback_disabled'),
            422
        );
    }

    public static function centralNotConfigured(): self
    {
        return new self(
            'central_credentials_missing',
            __('payments.routing_central_missing'),
            503
        );
    }
}
