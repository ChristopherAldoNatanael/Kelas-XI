<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Rekonsiliasi status pembayaran ke provider.
 *
 * Dipakai scheduler / aksi admin, bukan di jalur request pengguna, sehingga
 * tidak menambah latensi checkout.
 */
class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly MidtransPartnerGateway $partnerGateway,
        private readonly MidtransService $midtrans,
        private readonly PaymentAccountResolver $resolver,
        private readonly MidtransAccountGateway $gateway
    ) {}

    /**
     * @return array{checked:bool, changed:bool, note:string}
     */
    public function sync(Payment $payment): array
    {
        if ($payment->partner_id !== null) {
            $result = $this->partnerGateway->fetchStatus($payment);
        } else {
            try {
                $account = $this->resolver->resolveLocked($payment);
            } catch (PaymentRoutingException $e) {
                $payment->forceFill([
                    'last_reconciled_at' => now(),
                    'reconcile_note' => $e->getMessage(),
                ])->save();

                return [
                    'checked' => false,
                    'changed' => false,
                    'note' => $e->getMessage(),
                ];
            }

            // Payment dengan route (klinik/fallback pusat) wajib ditanyakan
            // ke akun yang sama; hanya transaksi legacy tanpa route yang
            // memakai kredensial pusat lama.
            $result = $account !== null
                ? $this->gateway->getTransactionStatus($account, (string) $payment->provider_order_id)
                : $this->midtrans->getTransactionStatus($payment->provider_order_id);
        }

        $payment->forceFill(['last_reconciled_at' => now()])->save();

        if (!($result['success'] ?? false)) {
            $payment->forceFill(['reconcile_note' => $result['message'] ?? 'Provider tidak merespons'])->save();

            return [
                'checked' => false,
                'changed' => false,
                'note' => $result['message'] ?? 'Provider tidak merespons',
            ];
        }

        $data = $result['data'] ?? [];

        if (isset($data['gross_amount'])) {
            $providerGross = (int) round((float) $data['gross_amount']);
            if ($providerGross !== (int) $payment->gross_amount) {
                Log::warning('Payment reconciliation gross mismatch', [
                    'payment_id' => $payment->id,
                    'provider_gross' => $providerGross,
                    'internal_gross' => $payment->gross_amount,
                ]);

                $payment->forceFill(['reconcile_note' => 'Nominal provider tidak cocok dengan catatan internal'])->save();

                return [
                    'checked' => true,
                    'changed' => false,
                    'note' => 'Nominal provider tidak cocok dengan catatan internal',
                ];
            }
        }

        if (isset($data['currency']) && strtoupper((string) $data['currency']) !== strtoupper((string) $payment->currency)) {
            $payment->forceFill(['reconcile_note' => 'Currency provider tidak cocok'])->save();

            return ['checked' => true, 'changed' => false, 'note' => 'Currency provider tidak cocok'];
        }

        if (isset($data['merchant_id']) && filled($payment->merchant_id)
            && (string) $data['merchant_id'] !== (string) $payment->merchant_id) {
            $payment->forceFill(['reconcile_note' => 'Merchant ID provider tidak cocok'])->save();

            return ['checked' => true, 'changed' => false, 'note' => 'Merchant ID provider tidak cocok'];
        }

        $applied = $this->payments->applyProviderNotification($payment, $data, 'reconciliation');

        $payment->forceFill([
            'reconcile_note' => $applied['changed'] ? 'Status diperbarui dari provider' : 'Status sudah sesuai',
        ])->save();

        return [
            'checked' => true,
            'changed' => $applied['changed'],
            'note' => $applied['changed'] ? 'Status diperbarui dari provider' : 'Status sudah sesuai',
        ];
    }

    /**
     * @return array{checked:int, changed:int, failed:int}
     */
    public function syncPending(int $limit = 50): array
    {
        $stats = ['checked' => 0, 'changed' => 0, 'failed' => 0];

        Payment::pendingCheck()
            ->where(function ($query) {
                $query->whereNull('last_reconciled_at')
                    ->orWhere('last_reconciled_at', '<', now()->subMinutes(15));
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Payment $payment) use (&$stats) {
                try {
                    $result = $this->sync($payment);
                    if ($result['checked']) {
                        $stats['checked']++;
                    } else {
                        $stats['failed']++;
                    }
                    if ($result['changed']) {
                        $stats['changed']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    report($e);
                }
            });

        return $stats;
    }
}
