<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicMerchantConfig;
use App\Models\MedicalRecord;
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
 * Semua transaksi online (booking DP/lunas/pelunasan dan Pet Shop) memakai
 * satu jalur: PaymentAccountResolver menentukan akun Midtrans, lalu
 * MidtransAccountGateway membuat transaksi dengan kredensial akun tersebut.
 *
 * Tidak ada token yang dibuat dengan Server Key global tanpa keputusan
 * routing eksplisit, sehingga dana tidak pernah berpindah ke merchant yang
 * salah hanya karena konfigurasi klinik belum lengkap.
 */
class PaymentService
{
    public function __construct(
        private readonly MidtransPartnerGateway $partnerGateway,
        private readonly ProductStockService $stock,
        private readonly PaymentStatusService $bookingPaymentStatus,
        private readonly ProductOrderService $orders,
        private readonly PaymentAccountResolver $resolver,
        private readonly MidtransAccountGateway $gateway
    ) {}

    /**
     * Ketersediaan pembayaran online sebuah klinik.
     *
     * @return array{available:bool, reason:?string, provider:?string, environment:string, source:?string, merchant_label:?string, commission_percent:float}
     */
    public function onlineAvailability(Clinic $clinic): array
    {
        $availability = $this->resolver->availability($clinic);
        $config = ClinicMerchantConfig::where('clinic_id', $clinic->id)->first();

        return $availability + [
            'commission_percent' => (float) ($config?->commission_percent ?? 0),
        ];
    }

    /**
     * Buat transaksi online untuk booking (DP, lunas, atau pelunasan).
     *
     * Nominal SELALU dihitung dari kolom booking di database, bukan dari
     * request. `$requestedOrderId` hanya dipakai bila formatnya cocok dengan
     * booking & jenis pembayaran; selain itu server membuat order id sendiri.
     */
    public function createBookingPayment(
        Booking $booking,
        string $kind,
        ?string $requestedOrderId = null
    ): Payment {
        $booking->loadMissing(['pet', 'user']);

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            throw new RuntimeException(__('payments.booking_not_payable'));
        }

        if ($booking->payment_status === 'paid') {
            throw new RuntimeException(__('payments.booking_already_paid'));
        }

        $amount = $this->bookingAmountFor($booking, $kind);

        $reusable = $this->findReusableBookingPayment($booking, $amount);

        if ($reusable) {
            return $reusable;
        }

        // Idempotensi: request ulang dengan order id yang sama tidak membuat
        // transaksi (dan tagihan) kedua.
        if ($requestedOrderId !== null && $requestedOrderId !== '') {
            $existing = Payment::where('provider_order_id', $requestedOrderId)->first();

            if ($existing
                && $existing->payable_type === Booking::class
                && (int) $existing->payable_id === (int) $booking->id
                && (int) $existing->gross_amount === $amount
                && in_array($existing->status, [Payment::STATUS_UNPAID, Payment::STATUS_PENDING], true)) {
                return $existing;
            }
        }

        $lockedPayment = $kind === 'remaining'
            ? $this->resolver->lockedRouteForBooking($booking->id)
            : null;

        try {
            $account = $this->resolver->resolve($booking->clinic_id, $lockedPayment);
        } catch (PaymentRoutingException $e) {
            $this->resolver->logRouting(
                $booking->clinic_id,
                [],
                \App\Models\PaymentRoutingLog::OUTCOME_REJECTED,
                $e->reason,
                null,
                Booking::class,
                $booking->id
            );

            throw $e;
        }

        $orderId = $this->bookingOrderId($booking, $kind, $requestedOrderId);

        $payment = Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_CONSULTATION,
            'clinic_id' => $booking->clinic_id,
            'user_id' => $booking->user_id,
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'provider_order_id' => $orderId,
            'payment_account_id' => $account->paymentAccountId,
            'payment_credential_version_id' => $account->credentialVersionId,
            'routing_source' => $account->routingSource,
            'merchant_id' => $account->merchantId,
            'environment' => $account->environment,
            'gross_amount' => $amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_UNPAID,
            'provider_status' => 'created',
            'expires_at' => now()->addMinutes(30),
            'metadata' => [
                'booking_id' => $booking->id,
                'intent' => $kind,
                'gateway' => 'midtrans_' . $account->routingSource,
                'merchant_label' => $account->label,
            ],
        ]);

        $this->recordHistory($payment, null, Payment::STATUS_UNPAID, 'system', 'Payment booking dibuat');

        $this->resolver->logRouting(
            $booking->clinic_id,
            $account->toLogContext(),
            \App\Models\PaymentRoutingLog::OUTCOME_RESOLVED,
            null,
            $payment,
            Booking::class,
            $booking->id
        );

        $result = $this->gateway->createTransaction(
            $account,
            $this->bookingSnapPayload($booking, $payment, $kind)
        );

        if (!($result['success'] ?? false)) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'provider_status' => 'create_failed',
            ]);
            $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_FAILED, 'system', $result['message'] ?? 'Gagal membuat transaksi');

            throw new PaymentGatewayException($result['message'] ?? __('payments.gateway_failed'));
        }

        $payment->update([
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
            'snap_token' => $result['data']['token'] ?? null,
            'redirect_url' => $result['data']['redirect_url'] ?? null,
            'provider_transaction_id' => $result['data']['transaction_id'] ?? null,
        ]);

        $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_PENDING, 'system', 'Menunggu pembayaran');

        return $payment->fresh();
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

        try {
            $account = $this->resolver->resolve($order->clinic_id);
        } catch (PaymentRoutingException $e) {
            $this->resolver->logRouting(
                $order->clinic_id,
                [],
                \App\Models\PaymentRoutingLog::OUTCOME_REJECTED,
                $e->reason,
                null,
                ProductOrder::class,
                $order->id
            );

            throw $e;
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

        $payment = Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_PET_SHOP,
            'clinic_id' => $order->clinic_id,
            'user_id' => $order->user_id,
            'payable_type' => ProductOrder::class,
            'payable_id' => $order->id,
            'provider_order_id' => $this->makeProviderOrderId($order),
            'payment_account_id' => $account->paymentAccountId,
            'payment_credential_version_id' => $account->credentialVersionId,
            'routing_source' => $account->routingSource,
            'merchant_id' => $account->merchantId,
            'environment' => $account->environment,
            'gross_amount' => (int) $order->total_amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_UNPAID,
            'provider_status' => 'created',
            'expires_at' => now()->addMinutes((int) config('petshop.online_reservation_minutes', 30)),
            'metadata' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'gateway' => 'midtrans_' . $account->routingSource,
                'merchant_label' => $account->label,
            ],
        ]);

        $this->recordHistory($payment, null, Payment::STATUS_UNPAID, 'system', 'Payment dibuat');

        $this->resolver->logRouting(
            $order->clinic_id,
            $account->toLogContext(),
            \App\Models\PaymentRoutingLog::OUTCOME_RESOLVED,
            null,
            $payment,
            ProductOrder::class,
            $order->id
        );

        $result = $this->gateway->createTransaction(
            $account,
            $this->petShopSnapPayload($order, $payment)
        );

        if (!($result['success'] ?? false)) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'provider_status' => 'create_failed',
            ]);
            $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_FAILED, 'system', $result['message'] ?? 'Gagal membuat transaksi');

            throw new PaymentGatewayException($result['message'] ?? 'Gagal membuat transaksi pembayaran.');
        }

        $payment->update([
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
            'snap_token' => $result['data']['token'] ?? null,
            'redirect_url' => $result['data']['redirect_url'] ?? null,
            'provider_transaction_id' => $result['data']['transaction_id'] ?? null,
        ]);

        $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_PENDING, 'system', 'Menunggu pembayaran');

        if ($order->payment_status === ProductOrder::PAYMENT_UNPAID) {
            $order->forceFill(['payment_status' => ProductOrder::PAYMENT_PENDING])->save();
        }

        return $payment->fresh();
    }

    /**
     * Buat (atau pakai ulang) pembayaran online biaya tambahan rekam medis.
     *
     * Sebelumnya endpoint MEDREC memanggil `MidtransService` dengan Server Key
     * pusat, sehingga biaya tambahan klinik masuk ke merchant pusat. Sekarang
     * transaksi mengikuti jalur yang sama dengan booking/Pet Shop:
     * resolver menentukan akun, gateway membuat transaksi, dan baris Payment
     * menyimpan route tersebut untuk webhook & rekonsiliasi.
     */
    public function createMedicalRecordPayment(MedicalRecord $record): Payment
    {
        $record->loadMissing(['pet', 'booking.user', 'booking.clinic']);

        if (in_array($record->extra_payment_status, ['paid', 'not_required'], true)) {
            throw new RuntimeException(__('payments.medical_record_not_payable'));
        }

        $amount = (int) round(max(
            0,
            (float) $record->extra_payment_amount - (float) $record->extra_payment_paid_amount
        ));

        if ($amount <= 0) {
            throw new RuntimeException(__('payments.medical_record_nothing_to_pay'));
        }

        $booking = $record->booking;

        if ($booking === null) {
            throw new RuntimeException(__('payments.medical_record_booking_missing'));
        }

        $reusable = Payment::where('payable_type', MedicalRecord::class)
            ->where('payable_id', $record->id)
            ->where('gross_amount', $amount)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('redirect_url')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($reusable) {
            return $reusable;
        }

        $clinicId = $record->clinic_id ?? $booking->clinic_id;

        try {
            $account = $this->resolver->resolve($clinicId);
        } catch (PaymentRoutingException $e) {
            $this->resolver->logRouting(
                $clinicId,
                [],
                \App\Models\PaymentRoutingLog::OUTCOME_REJECTED,
                $e->reason,
                null,
                MedicalRecord::class,
                $record->id
            );

            throw $e;
        }

        $orderId = $this->medicalRecordOrderId($record);

        $payment = Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_MEDICAL_RECORD,
            'clinic_id' => $clinicId,
            'user_id' => $booking->user_id,
            'payable_type' => MedicalRecord::class,
            'payable_id' => $record->id,
            'provider_order_id' => $orderId,
            'payment_account_id' => $account->paymentAccountId,
            'payment_credential_version_id' => $account->credentialVersionId,
            'routing_source' => $account->routingSource,
            'merchant_id' => $account->merchantId,
            'environment' => $account->environment,
            'gross_amount' => $amount,
            'currency' => 'IDR',
            'status' => Payment::STATUS_UNPAID,
            'provider_status' => 'created',
            'expires_at' => now()->addMinutes(30),
            'metadata' => [
                'medical_record_id' => $record->id,
                'booking_id' => $booking->id,
                'intent' => 'medical_record_extra',
                'gateway' => 'midtrans_' . $account->routingSource,
                'merchant_label' => $account->label,
            ],
        ]);

        $this->recordHistory($payment, null, Payment::STATUS_UNPAID, 'system', 'Payment biaya tambahan dibuat');

        $this->resolver->logRouting(
            $clinicId,
            $account->toLogContext(),
            \App\Models\PaymentRoutingLog::OUTCOME_RESOLVED,
            null,
            $payment,
            MedicalRecord::class,
            $record->id
        );

        $result = $this->gateway->createTransaction(
            $account,
            $this->medicalRecordSnapPayload($record, $payment)
        );

        if (!($result['success'] ?? false)) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'provider_status' => 'create_failed',
            ]);
            $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_FAILED, 'system', $result['message'] ?? 'Gagal membuat transaksi');

            throw new PaymentGatewayException($result['message'] ?? __('payments.gateway_failed'));
        }

        $payment->update([
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
            'snap_token' => $result['data']['token'] ?? null,
            'redirect_url' => $result['data']['redirect_url'] ?? null,
            'provider_transaction_id' => $result['data']['transaction_id'] ?? null,
        ]);

        $this->recordHistory($payment, Payment::STATUS_UNPAID, Payment::STATUS_PENDING, 'system', 'Menunggu pembayaran');

        $record->forceFill([
            'extra_payment_status' => 'pending',
            'extra_payment_order_id' => $orderId,
        ])->save();

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

        // Tunai/legacy tanpa route terkunci: refund dikonfirmasi manual.
        if ($payment->provider !== 'midtrans' || ($payment->partner_id === null && $payment->routing_source === null)) {
            $refund->forceFill([
                'note' => 'Refund manual: konfirmasi setelah dana dikembalikan ke pembeli.',
            ])->save();

            return ['success' => true, 'message' => null, 'refund' => $refund->fresh()];
        }

        if ($payment->partner_id !== null) {
            $result = $this->partnerGateway->refund($payment->fresh(), $amount, $reason);
        } else {
            $account = $this->resolver->resolveLocked($payment->fresh());
            $result = $account
                ? $this->gateway->refund($account, (string) $payment->provider_order_id, $amount, $reason)
                : ['success' => false, 'message' => 'Route pembayaran tidak ditemukan.'];
        }

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

    private function bookingAmountFor(Booking $booking, string $kind): int
    {
        $paid = (float) $booking->paid_amount;

        return match ($kind) {
            'dp' => $this->dpAmount($booking),
            'full' => $this->fullAmount($booking, $paid),
            'remaining' => $this->remainingAmount($booking, $paid),
            default => throw new RuntimeException(__('payments.invalid_payment_intent')),
        };
    }

    private function dpAmount(Booking $booking): int
    {
        if ($booking->payment_type !== 'dp' || (float) $booking->dp_amount <= 0) {
            throw new RuntimeException(__('payments.not_dp_booking'));
        }

        if ((float) $booking->paid_amount > 0) {
            throw new RuntimeException(__('payments.dp_already_recorded'));
        }

        return max(0, (int) round((float) $booking->dp_amount));
    }

    private function fullAmount(Booking $booking, float $paid): int
    {
        if ($paid > 0) {
            throw new RuntimeException(__('payments.partial_already_recorded'));
        }

        $amount = (int) round((float) $booking->total_amount - $paid);

        if ($amount <= 0) {
            throw new RuntimeException(__('payments.nothing_to_pay'));
        }

        return $amount;
    }

    private function remainingAmount(Booking $booking, float $paid): int
    {
        if ($booking->payment_type !== 'dp') {
            throw new RuntimeException(__('payments.not_dp_booking'));
        }

        if ($paid <= 0) {
            throw new RuntimeException(__('payments.complete_first_payment'));
        }

        $amount = (int) round(max(0, (float) ($booking->remaining_amount ?? 0)));

        if ($amount <= 0) {
            throw new RuntimeException(__('payments.nothing_to_pay'));
        }

        return $amount;
    }

    private function findReusableBookingPayment(Booking $booking, int $amount): ?Payment
    {
        return Payment::where('payable_type', Booking::class)
            ->where('payable_id', $booking->id)
            ->where('gross_amount', $amount)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('redirect_url')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    private function bookingOrderId(Booking $booking, string $kind, ?string $requested): string
    {
        $millis = (int) (microtime(true) * 1000);

        if ($requested !== null && $requested !== '') {
            $pattern = $kind === 'remaining'
                ? '/^BOOKING-' . $booking->id . '-REMAINING-\\d+$/'
                : '/^BOOKING-' . $booking->id . '-\\d+$/';

            if (preg_match($pattern, $requested)) {
                return $requested;
            }
        }

        return $kind === 'remaining'
            ? 'BOOKING-' . $booking->id . '-REMAINING-' . $millis
            : 'BOOKING-' . $booking->id . '-' . $millis;
    }

    private function bookingSnapPayload(Booking $booking, Payment $payment, string $kind): array
    {
        $petName = $booking->pet?->name ?: 'Pet';
        $label = match ($kind) {
            'dp' => 'DP Booking',
            'remaining' => 'Pelunasan Booking',
            default => 'Pembayaran Booking',
        };

        return [
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => (int) $payment->gross_amount,
            ],
            'customer_details' => [
                'first_name' => $booking->user?->name ?? 'Pelanggan',
                'email' => $booking->user?->email,
            ],
            'item_details' => [[
                'id' => ($kind === 'remaining' ? 'REMAINING-' : 'BOOKING-') . $booking->id,
                'price' => (int) $payment->gross_amount,
                'quantity' => 1,
                'name' => $label . ' - ' . $petName,
            ]],
            'callbacks' => [
                'finish' => 'https://app.sandbox.midtrans.com/payment/finish',
            ],
            'enabled_payments' => $this->enabledPayments(),
            'credit_card' => ['secure' => true],
        ];
    }

    private function medicalRecordOrderId(MedicalRecord $record): string
    {
        // Milidetik menjaga dua tap bersamaan tidak menabrak kolom unik
        // `extra_payment_order_id` / `payment_events.order_id`.
        return 'MEDREC-' . $record->id . '-' . (int) (microtime(true) * 1000);
    }

    private function medicalRecordSnapPayload(MedicalRecord $record, Payment $payment): array
    {
        $petName = $record->pet?->name ?: 'Pet';
        $booking = $record->booking;

        return [
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => (int) $payment->gross_amount,
            ],
            'customer_details' => [
                'first_name' => $booking?->user?->name ?? 'Pelanggan',
                'email' => $booking?->user?->email,
            ],
            'item_details' => [[
                'id' => 'MEDREC-' . $record->id,
                'price' => (int) $payment->gross_amount,
                'quantity' => 1,
                'name' => 'Biaya Tambahan - ' . $petName,
            ]],
            'callbacks' => [
                'finish' => 'https://app.sandbox.midtrans.com/payment/finish',
            ],
            'enabled_payments' => $this->enabledPayments(),
            'credit_card' => ['secure' => true],
        ];
    }

    private function petShopSnapPayload(ProductOrder $order, Payment $payment): array
    {
        return [
            'transaction_details' => [
                'order_id' => $payment->provider_order_id,
                'gross_amount' => (int) $payment->gross_amount,
            ],
            'customer_details' => [
                'first_name' => $order->customer_name ?: 'Pelanggan',
                'phone' => $order->customer_phone ?: null,
            ],
            'item_details' => [[
                'id' => 'SHOP-' . $order->id,
                'price' => (int) $payment->gross_amount,
                'quantity' => 1,
                'name' => 'Pesanan ' . $order->order_number,
            ]],
            'callbacks' => [
                'finish' => 'https://app.sandbox.midtrans.com/payment/finish',
            ],
            'enabled_payments' => $this->enabledPayments(),
            'credit_card' => ['secure' => true],
        ];
    }

    private function enabledPayments(): array
    {
        return [
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
        ];
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

        if ($payment->payable_type === MedicalRecord::class) {
            $record = MedicalRecord::whereKey($payment->payable_id)->lockForUpdate()->first();

            if (!$record) {
                Log::warning('Payment has no matching medical record', ['payment_id' => $payment->id]);
                return;
            }

            // Aturan dana tambahan tetap sama (event unik per order id,
            // status tidak mundur). Payment row sekarang menjadi sumber
            // status yang sama dengan domain lain.
            $this->bookingPaymentStatus->applyMedicalRecordTransactionStatus(
                $record,
                (string) $payment->provider_order_id,
                (string) ($payload['transaction_status'] ?? $payment->provider_status ?? ''),
                $payment->payment_method,
                $payload['gross_amount'] ?? $payment->gross_amount,
                $payload
            );

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
