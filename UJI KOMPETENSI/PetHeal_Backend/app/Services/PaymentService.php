<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicMerchantConfig;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\PaymentRefund;
use App\Models\PaymentStatusHistory;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Payment Service terpusat.
 *
 * Melayani `consultation` dan `pet_shop` dengan satu tabel `payments`, tetapi
 * efek samping tetap dijalankan pada domain masing-masing. Tidak ada
 * kesimpulan "lunas" dari redirect frontend: status hanya berubah dari
 * notifikasi provider yang tervalidasi atau rekonsiliasi server-to-server.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentSplitCalculator $splitCalculator,
        private readonly MidtransPartnerGateway $partnerGateway,
        private readonly MerchantConfigurationService $merchants,
        private readonly ProductStockService $stock,
        private readonly PaymentStatusService $bookingPaymentStatus,
        private readonly ProductOrderService $orders
    ) {}

    public function onlineAvailability(Clinic $clinic): array
    {
        return $this->merchants->onlineAvailability($clinic, $this->partnerGateway);
    }

    /**
     * Buat (atau pakai ulang) payment online untuk satu pesanan Pet Shop.
     */
    public function createPetShopPayment(ProductOrder $order): Payment
    {
        if ($order->isFinal() || $order->isPaid()) {
            throw new RuntimeException('Pesanan ini tidak dapat dibayar lagi.');
        }

        $order->loadMissing('clinic');
        $availability = $this->onlineAvailability($order->clinic);

        if (!$availability['available']) {
            throw new RuntimeException('Pembayaran online belum tersedia untuk klinik ini.');
        }

        $existing = $order->payments()
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('redirect_url')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $merchantConfig = ClinicMerchantConfig::where('clinic_id', $order->clinic_id)->firstOrFail();

        $split = $this->splitCalculator->calculate(
            (int) $order->total_amount,
            (float) $merchantConfig->commission_percent,
            (string) $merchantConfig->merchant_id,
            $this->partnerGateway->platformMerchantId()
        );

        $payment = Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_PET_SHOP,
            'clinic_id' => $order->clinic_id,
            'user_id' => $order->user_id,
            'payable_type' => ProductOrder::class,
            'payable_id' => $order->id,
            'provider_order_id' => $this->makeProviderOrderId($order),
            'partner_id' => $this->partnerGateway->partnerId(),
            'merchant_id' => $merchantConfig->merchant_id,
            'environment' => (string) config('services.midtrans.environment', 'sandbox'),
            'gross_amount' => (int) $order->total_amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_UNPAID,
            'expires_at' => now()->addMinutes((int) config('petshop.online_reservation_minutes', 30)),
            'split_settings' => $split['settings'],
            'metadata' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'commission_percent' => $split['commission_percent'],
                'clinic_amount' => $split['clinic_amount'],
                'platform_amount' => $split['platform_amount'],
                'gateway' => 'midtrans_partner',
            ],
        ]);

        $this->recordHistory($payment, null, Payment::STATUS_UNPAID, 'system', 'Payment dibuat');

        $result = $this->partnerGateway->createTransaction($payment);

        if (!($result['success'] ?? false)) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'provider_status' => 'create_failed',
            ]);
            $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_FAILED, 'system', $result['message'] ?? 'Gagal membuat transaksi');

            throw new RuntimeException($result['message'] ?? 'Gagal membuat transaksi pembayaran.');
        }

        $payment->update([
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
            'snap_token' => $result['token'] ?? null,
            'redirect_url' => $result['redirect_url'] ?? null,
            'provider_transaction_id' => $result['transaction_id'] ?? null,
        ]);

        $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_PENDING, 'system', 'Menunggu pembayaran');

        if ($order->payment_status === ProductOrder::PAYMENT_UNPAID) {
            $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PENDING])->save();
        }

        return $payment->fresh();
    }

    /**
     * Catat pembayaran tunai di klinik (tanpa provider eksternal).
     */
    public function recordCashPayment(ProductOrder $order, ?User $actor = null): Payment
    {
        $providerOrderId = 'CASH-' . $order->order_number;

        $payment = Payment::firstOrCreate(
            ['provider_order_id' => $providerOrderId],
            [
                'payment_code' => Payment::generatePaymentCode(),
                'provider' => 'cash',
                'payment_type' => Payment::TYPE_PET_SHOP,
                'clinic_id' => $order->clinic_id,
                'user_id' => $order->user_id,
                'payable_type' => ProductOrder::class,
                'payable_id' => $order->id,
                'environment' => (string) config('services.midtrans.environment', 'sandbox'),
                'gross_amount' => (int) $order->total_amount,
                'currency' => 'IDR',
                'payment_method' => 'cash',
                'status' => Payment::STATUS_PAID,
                'provider_status' => 'paid_at_clinic',
                'paid_at' => now(),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'gateway' => 'cash',
                    'recorded_by' => $actor?->id,
                ],
            ]
        );

        if ($payment->wasRecentlyCreated) {
            $this->recordHistory($payment, null, Payment::STATUS_PAID, 'clinic_admin', 'Pembayaran tunai di klinik');
        }

        if ($order->payment_status !== ProductOrder::PAYMENT_PAID) {
            $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PAID])->save();
        }

        return $payment;
    }

    /**
     * Write-through: transaksi konsultasi lama tetap berjalan, tetapi kini
     * tercatat di domain payment yang sama.
     */
    public function recordConsultationPayment(
        Booking $booking,
        string $providerOrderId,
        int $grossAmount,
        ?string $paymentMethod = null
    ): Payment {
        $existing = Payment::where('provider_order_id', $providerOrderId)->first();
        if ($existing) {
            return $existing;
        }

        $merchantConfig = ClinicMerchantConfig::where('clinic_id', $booking->clinic_id)->first();

        $payment = Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_CONSULTATION,
            'clinic_id' => $booking->clinic_id,
            'user_id' => $booking->user_id,
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'provider_order_id' => $providerOrderId,
            'merchant_id' => $merchantConfig?->merchant_id,
            'partner_id' => $merchantConfig?->partner_id,
            'environment' => (string) config('services.midtrans.environment', 'sandbox'),
            'gross_amount' => max(0, $grossAmount),
            'currency' => 'IDR',
            'payment_method' => $paymentMethod,
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
            'metadata' => [
                'booking_id' => $booking->id,
                // Konsultasi masih memakai merchant yang sudah terpasang.
                // Migrasi ke merchant klinik menunggu onboarding Partner.
                'gateway' => $merchantConfig?->isReadyForOnlinePayment() ? 'midtrans_partner' : 'legacy_merchant',
            ],
        ]);

        $this->recordHistory($payment, null, Payment::STATUS_PENDING, 'system', 'Token pembayaran konsultasi dibuat');

        return $payment;
    }

    /**
     * Terapkan status provider ke payment + domain terkait.
     *
     * @return array{changed:bool, status:string}
     */
    public function applyProviderNotification(Payment $payment, array $payload, string $source = 'webhook'): array
    {
        $providerStatus = strtolower(trim((string) ($payload['transaction_status'] ?? '')));
        $internalStatus = $this->mapProviderStatus($providerStatus, $payload);

        if ($internalStatus === null) {
            return ['changed' => false, 'status' => $payment->status];
        }

        return DB::transaction(function () use ($payment, $payload, $providerStatus, $internalStatus, $source) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $isMoneyPositive = (float) ($payload['gross_amount'] ?? 0) > 0;

            if ($internalStatus === Payment::STATUS_PAID && !$isMoneyPositive) {
                Log::warning('Ignoring paid notification with non-positive amount', [
                    'payment_id' => $locked->id,
                    'order_id' => $locked->provider_order_id,
                ]);

                return ['changed' => false, 'status' => $locked->status];
            }

            if ($locked->status === $internalStatus) {
                $locked->forceFill([
                    'provider_status' => $providerStatus,
                    'provider_transaction_id' => $payload['transaction_id'] ?? $locked->provider_transaction_id,
                ])->save();

                return ['changed' => false, 'status' => $locked->status];
            }

            if (!$locked->canMoveTo($internalStatus)) {
                Log::info('Ignoring backwards payment status', [
                    'payment_id' => $locked->id,
                    'from' => $locked->status,
                    'to' => $internalStatus,
                    'provider' => $providerStatus,
                ]);

                return ['changed' => false, 'status' => $locked->status];
            }

            $from = $locked->status;
            $attributes = [
                'status' => $internalStatus,
                'provider_status' => $providerStatus,
                'provider_transaction_id' => $payload['transaction_id'] ?? $locked->provider_transaction_id,
                'payment_method' => $payload['payment_type'] ?? $locked->payment_method,
            ];

            if ($internalStatus === Payment::STATUS_PAID && $locked->paid_at === null) {
                $attributes['paid_at'] = now();
            }

            if (in_array($internalStatus, [
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ], true)) {
                $refundAmount = (int) round((float) ($payload['refund_amount'] ?? 0));
                $attributes['refunded_amount'] = $internalStatus === Payment::STATUS_REFUNDED
                    ? (int) $locked->gross_amount
                    : min((int) $locked->gross_amount, (int) $locked->refunded_amount + $refundAmount);
            }

            $locked->forceFill($attributes)->save();
            $this->recordHistory($locked, $from, $internalStatus, $source, 'Status provider: ' . $providerStatus);

            $this->applyToPayable($locked, $internalStatus, $payload);

            return ['changed' => true, 'status' => $internalStatus];
        });
    }

    /**
     * @return array{success:bool, message:?string, refund:?PaymentRefund}
     */
    public function requestRefund(Payment $payment, int $amount, string $reason, ?User $actor = null): array
    {
        if ($payment->payment_type !== Payment::TYPE_PET_SHOP) {
            return [
                'success' => false,
                'message' => 'Refund konsultasi diproses melalui alur pembatalan booking yang sudah ada.',
                'refund' => null,
            ];
        }

        $refund = DB::transaction(function () use ($payment, $amount, $reason, $actor) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (!$locked->isPaid()) {
                throw new RuntimeException('Pembayaran belum berhasil; tidak ada dana yang dapat dikembalikan.');
            }

            $remaining = (int) $locked->gross_amount - (int) $locked->refunded_amount;
            if ($amount <= 0 || $amount > $remaining) {
                throw new RuntimeException('Nominal refund melebihi sisa pembayaran.');
            }

            $refund = PaymentRefund::create([
                'payment_id' => $locked->id,
                'refund_code' => PaymentRefund::generateRefundCode(),
                'amount' => $amount,
                'reason' => $reason,
                'status' => PaymentRefund::STATUS_REQUESTED,
                'requested_by' => $actor?->id,
            ]);

            $locked->forceFill(['status' => Payment::STATUS_REFUND_PENDING])->save();
            $this->recordHistory($locked, Payment::STATUS_PAID, Payment::STATUS_REFUND_PENDING, 'clinic_admin', 'Refund diminta: ' . $reason);

            return $refund;
        });

        // Tunai/legacy tidak punya endpoint refund otomatis di sini: refund
        // harus dikonfirmasi manual setelah dana benar-benar dikembalikan.
        if ($payment->provider !== 'midtrans' || blank($payment->partner_id)) {
            $refund->forceFill([
                'note' => 'Refund manual: konfirmasi setelah dana dikembalikan ke pembeli.',
            ])->save();

            return ['success' => true, 'message' => null, 'refund' => $refund->fresh()];
        }

        $result = $this->partnerGateway->refund($payment->fresh(), $amount, $reason);

        if ($result['success'] ?? false) {
            $refund->forceFill([
                'status' => PaymentRefund::STATUS_PENDING,
                'provider_refund_id' => $result['refund_id'] ?? null,
            ])->save();

            return ['success' => true, 'message' => null, 'refund' => $refund->fresh()];
        }

        $refund->forceFill([
            'status' => PaymentRefund::STATUS_FAILED,
            'note' => $result['message'] ?? 'Provider menolak refund.',
        ])->save();

        return [
            'success' => false,
            'message' => $result['message'] ?? 'Refund ditolak provider.',
            'refund' => $refund->fresh(),
        ];
    }

    /**
     * Konfirmasi hasil refund (dari admin atau webhook provider).
     * Status payment TIDAK pernah menjadi refunded tanpa langkah ini.
     */
    public function confirmRefund(PaymentRefund $refund, bool $succeeded, ?string $note = null, ?User $actor = null): PaymentRefund
    {
        return DB::transaction(function () use ($refund, $succeeded, $note, $actor) {
            $locked = PaymentRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentRefund::STATUS_SUCCEEDED) {
                return $locked;
            }

            if (!$succeeded) {
                $locked->forceFill([
                    'status' => PaymentRefund::STATUS_FAILED,
                    'note' => $note,
                    'processed_at' => now(),
                ])->save();

                if ($payment->status === Payment::STATUS_REFUND_PENDING) {
                    $payment->forceFill(['status' => Payment::STATUS_PAID])->save();
                    $this->recordHistory($payment, Payment::STATUS_REFUND_PENDING, Payment::STATUS_PAID, 'clinic_admin', 'Refund gagal: ' . ($note ?? '-'));
                }

                return $locked;
            }

            $newRefunded = min((int) $payment->gross_amount, (int) $payment->refunded_amount + (int) $locked->amount);
            $newStatus = $newRefunded >= (int) $payment->gross_amount
                ? Payment::STATUS_REFUNDED
                : Payment::STATUS_PARTIALLY_REFUNDED;

            $locked->forceFill([
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'note' => $note,
                'processed_at' => now(),
            ])->save();

            $payment->forceFill([
                'refunded_amount' => $newRefunded,
                'status' => $newStatus,
            ])->save();

            $this->recordHistory($payment, Payment::STATUS_REFUND_PENDING, $newStatus, 'clinic_admin', 'Refund dikonfirmasi');

            if ($payment->payable_type === ProductOrder::class) {
                $order = ProductOrder::whereKey($payment->payable_id)->lockForUpdate()->first();
                if ($order) {
                    $order->forceFill([
                        'payment_status' => $newStatus === Payment::STATUS_REFUNDED
                            ? ProductOrder::PAYMENT_REFUNDED
                            : ProductOrder::PAYMENT_PARTIALLY_REFUNDED,
                    ])->save();
                }
            }

            return $locked;
        });
    }

    /**
     * Catat notifikasi mentah (termasuk yang signature-nya tidak valid) untuk
     * audit. Payload TIDAK berisi secret; hanya body provider.
     */
    public function logNotification(array $payload, bool $signatureValid, ?Payment $payment, ?string $note = null): PaymentNotification
    {
        return PaymentNotification::create([
            'payment_id' => $payment?->id,
            'provider' => 'midtrans',
            'provider_order_id' => $payload['order_id'] ?? null,
            'signature_valid' => $signatureValid,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'status_code' => (string) ($payload['status_code'] ?? ''),
            'gross_amount' => (string) ($payload['gross_amount'] ?? ''),
            'payload' => $payload,
            'processed' => false,
            'processing_note' => $note,
            'ip_address' => request()->ip(),
        ]);
    }

    public function recordHistory(Payment $payment, ?string $from, string $to, string $source, ?string $note = null, ?int $actorId = null): void
    {
        PaymentStatusHistory::create([
            'payment_id' => $payment->id,
            'from_status' => $from,
            'to_status' => $to,
            'provider_status' => $payment->provider_status,
            'source' => $source,
            'note' => $note,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Mapping status provider → status internal.
     */
    public function mapProviderStatus(string $providerStatus, array $payload): ?string
    {
        $fraud = strtolower(trim((string) ($payload['fraud_status'] ?? '')));

        return match ($providerStatus) {
            'settlement' => Payment::STATUS_PAID,
            'capture' => in_array($fraud, ['', 'accept'], true) ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
            'pending', 'authorize' => Payment::STATUS_PENDING,
            'deny', 'failure' => Payment::STATUS_FAILED,
            'cancel' => Payment::STATUS_CANCELLED,
            'expire' => Payment::STATUS_EXPIRED,
            'refund' => Payment::STATUS_REFUNDED,
            'partial_refund' => Payment::STATUS_PARTIALLY_REFUNDED,
            'chargeback' => Payment::STATUS_CHARGEBACK,
            default => null,
        };
    }

    private function applyToPayable(Payment $payment, string $internalStatus, array $payload): void
    {
        if ($payment->payable_type === ProductOrder::class) {
            $order = ProductOrder::whereKey($payment->payable_id)->lockForUpdate()->first();

            if (!$order) {
                Log::warning('Payment has no matching product order', ['payment_id' => $payment->id]);
                return;
            }

            if ($internalStatus === Payment::STATUS_PAID) {
                $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PAID])->save();

                if ($order->status === ProductOrder::STATUS_PENDING_PAYMENT) {
                    $from = $order->status;
                    $order->forceFill(['status' => ProductOrder::STATUS_AWAITING_CONFIRMATION])->save();
                    $this->orders->log($order, $from, ProductOrder::STATUS_AWAITING_CONFIRMATION, 'webhook', null, 'Pembayaran diterima');
                }

                return;
            }

            if (in_array($internalStatus, [
                Payment::STATUS_FAILED,
                Payment::STATUS_EXPIRED,
                Payment::STATUS_CANCELLED,
            ], true)) {
                if ($order->payment_status !== ProductOrder::PAYMENT_PAID) {
                    $mapped = match ($internalStatus) {
                        Payment::STATUS_FAILED => ProductOrder::PAYMENT_FAILED,
                        Payment::STATUS_EXPIRED => ProductOrder::PAYMENT_EXPIRED,
                        default => ProductOrder::PAYMENT_CANCELLED,
                    };
                    $order->forceFill(['payment_status' => $mapped])->save();

                    if ($order->status === ProductOrder::STATUS_PENDING_PAYMENT) {
                        $this->stock->release($order, 'release', 'Pembayaran gagal/kedaluwarsa');
                        $from = $order->status;
                        $order->forceFill([
                            'status' => ProductOrder::STATUS_EXPIRED,
                            'cancelled_at' => now(),
                            'cancellation_reason' => 'Pembayaran tidak diselesaikan',
                        ])->save();
                        $this->orders->log($order, $from, ProductOrder::STATUS_EXPIRED, 'webhook', null, 'Pembayaran gagal/kedaluwarsa');
                    }
                }

                return;
            }

            if ($internalStatus === Payment::STATUS_REFUND_PENDING) {
                $order->forceFill(['payment_status' => ProductOrder::PAYMENT_REFUND_PENDING])->save();
                return;
            }

            if ($internalStatus === Payment::STATUS_PARTIALLY_REFUNDED) {
                $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PARTIALLY_REFUNDED])->save();
                return;
            }

            if ($internalStatus === Payment::STATUS_REFUNDED) {
                $order->forceFill(['payment_status' => ProductOrder::PAYMENT_REFUNDED])->save();
                return;
            }

            if ($internalStatus === Payment::STATUS_CHARGEBACK) {
                $order->forceFill(['payment_status' => ProductOrder::PAYMENT_CHARGEBACK])->save();
            }

            return;
        }

        if ($payment->payable_type === Booking::class) {
            $booking = Booking::whereKey($payment->payable_id)->lockForUpdate()->first();

            if (!$booking) {
                Log::warning('Payment has no matching booking', ['payment_id' => $payment->id]);
                return;
            }

            // Booking tetap memakai aturan lama (DP/remaining, tidak mundur).
            $this->bookingPaymentStatus->applyTransactionStatus(
                $booking,
                $payment->provider_order_id,
                (string) ($payload['transaction_status'] ?? $payment->provider_status ?? ''),
                $payment->payment_method,
                $payload['gross_amount'] ?? $payment->gross_amount,
                $payload
            );
        }
    }

    private function makeProviderOrderId(ProductOrder $order): string
    {
        // Unik per percobaan (milidetik), tetap dapat ditelusuri ke order.
        return 'SHOP-' . $order->id . '-' . (int) (microtime(true) * 1000);
    }
}
