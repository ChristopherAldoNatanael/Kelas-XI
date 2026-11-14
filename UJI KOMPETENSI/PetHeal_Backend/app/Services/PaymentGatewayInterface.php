<?php

namespace App\Services;

use App\Models\Payment;

/**
 * Kontrak gateway pembayaran.
 *
 * Controller dan service bisnis tidak boleh memanggil HTTP provider secara
 * langsung; semua lewat kontrak ini agar mode (merchant tunggal / partner)
 * dapat diganti tanpa mengubah domain order.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @return array{success:bool, token?:string, redirect_url?:string, transaction_id?:?string, message?:string, status?:int}
     */
    public function createTransaction(Payment $payment): array;

    /**
     * @return array{success:bool, data?:array, message?:string, status?:int}
     */
    public function fetchStatus(Payment $payment): array;

    /**
     * @return array{success:bool, refund_id?:?string, status?:string, message?:string}
     */
    public function refund(Payment $payment, int $amount, string $reason): array;

    /**
     * Verifikasi signature notifikasi provider.
     */
    public function verifyNotification(array $payload): bool;
}
