<?php

namespace App\Services;

use RuntimeException;

/**
 * Provider (Midtrans) menolak/tidak dapat membuat transaksi.
 * Dipisahkan dari RuntimeException validasi supaya API dapat membalas 502
 * tanpa menebak dari isi pesan.
 */
class PaymentGatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }
}
