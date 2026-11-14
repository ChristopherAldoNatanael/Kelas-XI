<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductOrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup pesanan Pet Shop.
 *
 * Harga, diskon, dan total SELALU dihitung ulang dari database. Nominal dari
 * aplikasi tidak pernah dipercaya.
 */
class ProductOrderService
{
    public function __construct(
        private readonly ProductStockService $stock
    ) {}

    /**
     * @param array<int, array{variant_id:int, quantity:int}> $items
     */
    public function placeOrder(
        User $user,
        array $items,
        string $paymentMethod,
        ?string $notes = null,
        ?string $customerPhone = null
    ): ProductOrder {
        if (!in_array($paymentMethod, [ProductOrder::METHOD_ONLINE, ProductOrder::METHOD_PAY_AT_CLINIC], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak dikenal.',
            ]);
        }

        // Gabungkan baris dengan varian sama agar pemeriksaan stok tidak bisa
        // dilewati lewat dua baris yang masing-masing "masih cukup".
        $quantities = [];
        foreach ($items as $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Setiap item harus memiliki varian dan jumlah yang valid.',
                ]);
            }

            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + $quantity;
        }

        if (empty($quantities)) {
            throw ValidationException::withMessages(['items' => 'Keranjang tidak boleh kosong.']);
        }

        if (count($quantities) > (int) config('petshop.max_items_per_order', 20)) {
            throw ValidationException::withMessages(['items' => 'Jumlah jenis produk dalam satu pesanan terlalu banyak.']);
        }

        $maxQty = (int) config('petshop.max_quantity_per_item', 20);

        return DB::transaction(function () use ($user, $quantities, $paymentMethod, $notes, $customerPhone, $maxQty) {
            $variants = ProductVariant::with(['product.clinic'])
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($variants->count() !== count($quantities)) {
                throw ValidationException::withMessages(['items' => 'Ada produk yang sudah tidak tersedia.']);
            }

            $clinicIds = $variants->map(fn (ProductVariant $v) => $v->product?->clinic_id)->filter()->unique();

            if ($clinicIds->count() !== 1) {
                throw ValidationException::withMessages([
                    'items' => 'Satu pesanan hanya boleh berisi produk dari satu klinik.',
                ]);
            }

            /** @var Clinic|null $clinic */
            $clinic = $variants->first()->product->clinic;

            if (!$clinic || !$clinic->is_active) {
                throw ValidationException::withMessages(['items' => 'Klinik penjual sedang tidak aktif.']);
            }

            $subtotal = 0;
            $discountTotal = 0;
            $lines = [];

            foreach ($variants as $variant) {
                /** @var ClinicProduct $product */
                $product = $variant->product;
                $quantity = (int) $quantities[$variant->id];

                if ($quantity > $maxQty) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('Jumlah %s melebihi batas %d per pesanan.', $product->name, $maxQty),
                    ]);
                }

                if (!$product->is_active || $product->trashed() || !$variant->is_active) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('%s — %s sedang tidak dijual.', $product->name, $variant->name),
                    ]);
                }

                $available = $variant->availableStock();
                if ($available < $quantity) {
                    throw new InsufficientStockException($product->name, $variant->name, $quantity, $available);
                }

                $unitPrice = (int) $variant->price;
                $effectivePrice = $variant->effectivePrice();
                $lineDiscount = ($unitPrice - $effectivePrice) * $quantity;
                $lineSubtotal = $effectivePrice * $quantity;

                $subtotal += $unitPrice * $quantity;
                $discountTotal += $lineDiscount;

                $lines[] = [
                    'clinic_product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'image_path' => $product->image_path,
                    'unit_price' => $unitPrice,
                    'discount_price' => $variant->hasDiscount() ? $effectivePrice : null,
                    'quantity' => $quantity,
                    'subtotal' => $lineSubtotal,
                ];
            }

            // Bulatkan ke integer rupiah; tidak ada float yang disimpan.
            $total = $subtotal - $discountTotal;

            $expiresAt = $paymentMethod === ProductOrder::METHOD_ONLINE
                ? now()->addMinutes((int) config('petshop.online_reservation_minutes', 30))
                : now()->addHours((int) config('petshop.pay_at_clinic_hold_hours', 24));

            $status = $paymentMethod === ProductOrder::METHOD_ONLINE
                ? ProductOrder::STATUS_PENDING_PAYMENT
                : ProductOrder::STATUS_AWAITING_CONFIRMATION;

            $order = ProductOrder::create([
                'order_number' => $this->generateUniqueOrderNumber(),
                'user_id' => $user->id,
                'clinic_id' => $clinic->id,
                'status' => $status,
                'payment_status' => ProductOrder::PAYMENT_UNPAID,
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total_amount' => $total,
                'currency' => 'IDR',
                'pickup_clinic_name' => $clinic->name,
                'pickup_clinic_address' => (string) $clinic->address,
                'pickup_clinic_phone' => $clinic->phone,
                'customer_name' => $user->name,
                'customer_phone' => $customerPhone ?: $user->phone,
                'notes' => $notes,
                'reservation_expires_at' => $expiresAt,
            ]);

            foreach ($lines as $line) {
                ProductOrderItem::create($line + ['product_order_id' => $order->id]);
            }

            $this->log($order, null, $status, 'user', $user->id, 'Pesanan dibuat');

            $this->stock->reserve($order, $user->id);

            return $order->fresh(['items', 'clinic']);
        });
    }

    /**
     * Transisi status pesanan dengan efek samping stok/pembayaran.
     */
    public function transition(
        ProductOrder $order,
        string $toStatus,
        string $source,
        ?User $actor = null,
        ?string $note = null,
        ?string $reason = null
    ): ProductOrder {
        return DB::transaction(function () use ($order, $toStatus, $source, $actor, $note, $reason) {
            $locked = ProductOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $toStatus) {
                return $locked->fresh(['items', 'statusHistories', 'clinic']);
            }

            if (!$locked->canTransitionTo($toStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Status pesanan tidak dapat diubah dari %s ke %s.',
                        $locked->status,
                        $toStatus
                    ),
                ]);
            }

            $from = $locked->status;
            $attributes = ['status' => $toStatus];

            switch ($toStatus) {
                case ProductOrder::STATUS_ACCEPTED:
                    $attributes['confirmed_at'] = now();
                    break;

                case ProductOrder::STATUS_READY_FOR_PICKUP:
                    $attributes['ready_at'] = now();
                    break;

                case ProductOrder::STATUS_COMPLETED:
                    $this->stock->commit($locked, $actor?->id);
                    $attributes['completed_at'] = now();
                    if ($locked->isPayAtClinic()) {
                        $attributes['payment_status'] = ProductOrder::PAYMENT_PAID;
                    }
                    break;

                case ProductOrder::STATUS_REJECTED:
                    $this->stock->release($locked, 'release', 'Penolakan pesanan', $actor?->id);
                    $attributes['cancelled_at'] = now();
                    $attributes['cancellation_reason'] = $reason;
                    if ($locked->payment_status === ProductOrder::PAYMENT_PAID) {
                        $attributes['payment_status'] = ProductOrder::PAYMENT_REFUND_PENDING;
                    }
                    break;

                case ProductOrder::STATUS_CANCELLED:
                    $this->stock->release($locked, 'cancellation', 'Pembatalan pesanan', $actor?->id);
                    $attributes['cancelled_at'] = now();
                    $attributes['cancellation_reason'] = $reason;
                    if ($locked->payment_status === ProductOrder::PAYMENT_PAID) {
                        $attributes['payment_status'] = ProductOrder::PAYMENT_REFUND_PENDING;
                    }
                    break;

                case ProductOrder::STATUS_EXPIRED:
                    $this->stock->release($locked, 'release', 'Reservasi kedaluwarsa', $actor?->id);
                    $attributes['cancelled_at'] = now();
                    $attributes['cancellation_reason'] = 'Kedaluwarsa';
                    $attributes['payment_status'] = $locked->payment_status === ProductOrder::PAYMENT_PAID
                        ? ProductOrder::PAYMENT_REFUND_PENDING
                        : ProductOrder::PAYMENT_EXPIRED;
                    break;
            }

            $locked->forceFill($attributes)->save();
            $this->log($locked, $from, $toStatus, $source, $actor?->id, $note ?? $reason);

            return $locked->fresh(['items', 'statusHistories', 'clinic']);
        });
    }

    public function cancelByUser(ProductOrder $order, User $user, ?string $reason = null): ProductOrder
    {
        if ($order->user_id !== $user->id) {
            abort(403, 'Pesanan ini bukan milik Anda.');
        }

        if ($order->isPaid()) {
            throw ValidationException::withMessages([
                'status' => 'Pesanan yang sudah dibayar tidak dapat dibatalkan sendiri. Hubungi klinik untuk pengembalian dana.',
            ]);
        }

        if (!in_array($order->status, [
            ProductOrder::STATUS_PENDING_PAYMENT,
            ProductOrder::STATUS_AWAITING_CONFIRMATION,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Pesanan ini sudah diproses klinik dan tidak dapat dibatalkan.',
            ]);
        }

        return $this->transition($order, ProductOrder::STATUS_CANCELLED, 'user', $user, 'Dibatalkan pembeli', $reason);
    }

    /**
     * Dipanggil scheduler: lepas reservasi pesanan yang melewati batas waktu.
     *
     * @return int jumlah pesanan yang dikedaluwarsakan
     */
    public function expireStaleOrders(): int
    {
        $count = 0;

        ProductOrder::query()
            ->open()
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<', now())
            ->where('payment_status', '!=', ProductOrder::PAYMENT_PAID)
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    try {
                        $this->transition($order, ProductOrder::STATUS_EXPIRED, 'system', null, 'Reservasi kedaluwarsa');
                        $count++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        return $count;
    }

    public function log(
        ProductOrder $order,
        ?string $from,
        string $to,
        string $source,
        ?int $actorId = null,
        ?string $note = null
    ): void {
        ProductOrderStatusHistory::create([
            'product_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'source' => $source,
            'actor_id' => $actorId,
            'note' => $note,
        ]);
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $number = ProductOrder::generateOrderNumber();
        } while (ProductOrder::where('order_number', $number)->exists());

        return $number;
    }
}
