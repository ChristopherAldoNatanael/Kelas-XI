<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProductOrder;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Bukti pembayaran Pet Shop (digital receipt).
 *
 * Sumber data HANYA snapshot transaksi:
 * - `product_order_items` menyimpan nama/varian/harga saat pesanan dibuat;
 * - `product_orders.pickup_clinic_*` menyimpan alamat klinik saat itu;
 * - `payments` menyimpan metode, status, waktu lunas, dan identitas merchant.
 *
 * Perubahan nama produk atau harga katalog TIDAK boleh mengubah struk lama.
 */
class ReceiptService
{
    public function canIssue(ProductOrder $order): bool
    {
        return $order->hasSettledPayment();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ProductOrder $order): array
    {
        $order->loadMissing(['items', 'clinic', 'payments']);

        $payment = $this->settledPayment($order);
        $refunded = (int) ($payment?->refunded_amount ?? 0);
        $gross = (int) ($payment?->gross_amount ?? $order->total_amount);
        // Pembayaran dicatat admin bisa saja belum punya baris Payment (mis.
        // pesanan lama). Status "lunas" tetap dari order, nominal dari total.
        $paidAmount = $payment !== null
            ? max(0, $gross - $refunded)
            : ($order->hasSettledPayment() ? (int) $order->total_amount : 0);

        return [
            'receipt_number' => $this->receiptNumber($order),
            'order_number' => $order->order_number,
            'issued_at' => now()->toIso8601String(),
            'is_settled' => $order->hasSettledPayment(),
            'status_label' => $this->statusLabel($order),
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
            ],
            'clinic' => [
                'id' => $order->clinic_id,
                'name' => $order->pickup_clinic_name ?? $order->clinic?->name,
                'address' => $order->pickup_clinic_address ?? $order->clinic?->address,
                'phone' => $order->pickup_clinic_phone ?? $order->clinic?->phone,
                'logo_url' => $order->clinic?->logo_url,
                'primary_color' => $order->clinic?->primary_color,
            ],
            'pickup' => [
                'method' => 'clinic_pickup',
                'method_label' => 'Ambil di klinik',
                'clinic_name' => $order->pickup_clinic_name,
                'clinic_address' => $order->pickup_clinic_address,
                'clinic_phone' => $order->pickup_clinic_phone,
                'note' => 'Produk diambil langsung di klinik yang dipilih.',
            ],
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'unit_price' => (int) $item->unit_price,
                'discount_price' => $item->discount_price !== null ? (int) $item->discount_price : null,
                'quantity' => (int) $item->quantity,
                'subtotal' => (int) $item->subtotal,
            ])->values()->all(),
            'totals' => [
                'subtotal' => (int) $order->subtotal,
                'discount_total' => (int) $order->discount_total,
                'total_amount' => (int) $order->total_amount,
                'paid_amount' => $paidAmount,
                'refunded_amount' => $refunded,
                'outstanding_amount' => max(0, (int) $order->total_amount - $paidAmount),
                'currency' => $order->currency,
            ],
            'payment' => [
                'method' => $order->payment_method,
                'method_label' => $order->isPayAtClinic() ? 'Bayar di klinik' : 'Pembayaran online (Midtrans)',
                'status' => $order->payment_status,
                'receipt_status' => $order->hasSettledPayment() ? 'paid' : 'pending',
                'receipt_status_label' => $order->hasSettledPayment() ? 'LUNAS' : 'MENUNGGU PEMBAYARAN',
                'payment_code' => $payment?->payment_code,
                'provider_transaction_id' => $payment?->provider_transaction_id,
                'provider_status' => $payment?->provider_status,
                'paid_at' => $payment?->paid_at?->toIso8601String(),
                'settlement_at' => $payment?->paid_at?->toIso8601String(),
                'merchant_label' => $payment?->metadata['merchant_label'] ?? null,
                'merchant_id' => $payment?->merchant_id,
                'environment' => $payment?->environment,
                'payment_method_detail' => $payment?->payment_method,
            ],
            'timeline' => [
                'created_at' => $order->created_at?->toIso8601String(),
                'paid_at' => $payment?->paid_at?->toIso8601String(),
                'ready_at' => $order->ready_at?->toIso8601String(),
                'completed_at' => $order->completed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * PDF struk memakai template yang sama dengan tampilan di aplikasi.
     */
    public function renderPdf(ProductOrder $order): Dompdf
    {
        $receipt = $this->payload($order);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('receipts.pet-shop', ['receipt' => $receipt])->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    public function fileName(ProductOrder $order): string
    {
        // Hanya karakter aman: nomor pesanan dibuat server (A-Z, 0-9, '-').
        $safe = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $order->order_number);

        return 'PetHeal-Receipt-' . ($safe !== '' ? $safe : 'ORDER-' . $order->id) . '.pdf';
    }

    public function receiptNumber(ProductOrder $order): string
    {
        $date = $order->created_at ?? $order->updated_at ?? now();

        return 'PH-' . $date->format('ymd') . '-' . str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Pembayaran yang benar-benar menerima uang. Untuk pembayaran online hanya
     * baris berstatus paid yang dipakai; baris pending tidak pernah dianggap
     * lunas.
     */
    private function settledPayment(ProductOrder $order): ?Payment
    {
        return $order->payments
            ->filter(fn (Payment $payment) => in_array($payment->status, [
                Payment::STATUS_PAID,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ], true))
            ->sortByDesc(fn (Payment $payment) => $payment->paid_at?->timestamp ?? 0)
            ->first();
    }

    private function statusLabel(ProductOrder $order): string
    {
        return match (true) {
            $order->payment_status === ProductOrder::PAYMENT_PAID => 'Lunas',
            $order->payment_status === ProductOrder::PAYMENT_PENDING => 'Menunggu pembayaran',
            $order->payment_status === ProductOrder::PAYMENT_REFUNDED => 'Dana dikembalikan',
            $order->payment_status === ProductOrder::PAYMENT_PARTIALLY_REFUNDED => 'Dana dikembalikan sebagian',
            default => 'Belum dibayar',
        };
    }
}
