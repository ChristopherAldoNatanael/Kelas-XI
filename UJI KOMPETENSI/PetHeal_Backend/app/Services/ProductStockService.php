<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya pintu perubahan stok Pet Shop.
 *
 * Aturan:
 * - `available = stock_on_hand - stock_reserved`.
 * - Reservasi menahan stok tanpa mengubah stok fisik.
 * - Komitmen (barang diserahkan) mengurangi keduanya sekaligus — tidak pernah
 *   mengurangi stok fisik dua kali.
 * - Semua operasi memakai row lock dan menulis `product_stock_movements`.
 */
class ProductStockService
{
    public function reserve(ProductOrder $order, ?int $actorId = null): void
    {
        DB::transaction(function () use ($order, $actorId) {
            $locked = ProductOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Idempoten: reservasi hanya sekali per order.
            if ($locked->stock_reserved_at !== null
                || $locked->stock_committed_at !== null
                || $locked->stock_released_at !== null) {
                return;
            }

            $items = $locked->items()->orderBy('product_variant_id')->get();

            foreach ($items as $item) {
                $variant = $this->lockVariant($item->product_variant_id);

                $available = $variant->stock_on_hand - $variant->stock_reserved;
                if ($available < $item->quantity) {
                    throw new InsufficientStockException(
                        $item->product_name,
                        $item->variant_name,
                        (int) $item->quantity,
                        max(0, $available)
                    );
                }

                $variant->stock_reserved += (int) $item->quantity;
                $variant->save();

                $this->logMovement(
                    $variant,
                    ProductStockMovement::TYPE_RESERVATION,
                    (int) $item->quantity,
                    $locked,
                    null,
                    $actorId,
                    'Reservasi pesanan ' . $locked->order_number
                );
            }

            $locked->forceFill(['stock_reserved_at' => now()])->save();
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Lepaskan reservasi (pembatalan, penolakan, kedaluwarsa).
     */
    public function release(
        ProductOrder $order,
        string $type = ProductStockMovement::TYPE_RELEASE,
        ?string $note = null,
        ?int $actorId = null
    ): void {
        DB::transaction(function () use ($order, $type, $note, $actorId) {
            $locked = ProductOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock_committed_at !== null || $locked->stock_released_at !== null) {
                return;
            }

            if ($locked->stock_reserved_at !== null) {
                foreach ($locked->items()->orderBy('product_variant_id')->get() as $item) {
                    $variant = $this->lockVariant($item->product_variant_id);
                    $releaseQty = min((int) $item->quantity, (int) $variant->stock_reserved);
                    $variant->stock_reserved = max(0, $variant->stock_reserved - $releaseQty);
                    $variant->save();

                    // quantity negatif = reservasi berkurang.
                    $this->logMovement(
                        $variant,
                        $type,
                        -1 * $releaseQty,
                        $locked,
                        $note ?? 'Pelepasan reservasi ' . $locked->order_number,
                        $actorId
                    );
                }
            }

            $locked->forceFill(['stock_released_at' => now()])->save();
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Komitmen: barang benar-benar diserahkan ke pembeli.
     */
    public function commit(ProductOrder $order, ?int $actorId = null): void
    {
        DB::transaction(function () use ($order, $actorId) {
            $locked = ProductOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock_committed_at !== null) {
                return;
            }

            if ($locked->stock_released_at !== null) {
                throw new RuntimeException('Reservasi pesanan ini sudah dilepas; stok tidak dapat dikomit.');
            }

            if ($locked->stock_reserved_at === null) {
                throw new RuntimeException('Pesanan belum mereservasi stok; tidak dapat dikomit.');
            }

            foreach ($locked->items()->orderBy('product_variant_id')->get() as $item) {
                $variant = $this->lockVariant($item->product_variant_id);
                $qty = (int) $item->quantity;

                if ($variant->stock_on_hand < $qty) {
                    throw new InsufficientStockException(
                        $item->product_name,
                        $item->variant_name,
                        $qty,
                        (int) $variant->stock_on_hand
                    );
                }

                if ($variant->stock_reserved < $qty) {
                    throw new RuntimeException('Reservasi varian tidak konsisten dengan stok; periksa manual.');
                }

                $variant->stock_on_hand -= $qty;
                $variant->stock_reserved -= $qty;
                $variant->save();

                $this->logMovement(
                    $variant,
                    ProductStockMovement::TYPE_SALE,
                    -1 * $qty,
                    $locked,
                    'Penjualan ' . $locked->order_number,
                    $actorId
                );
            }

            $locked->forceFill(['stock_committed_at' => now()])->save();
            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Penyesuaian manual oleh admin klinik (stok masuk/koreksi).
     */
    public function adjust(
        ProductVariant $variant,
        int $delta,
        string $note,
        ?int $actorId,
        string $type = ProductStockMovement::TYPE_ADJUSTMENT
    ): ProductVariant {
        if ($delta === 0) {
            throw new RuntimeException('Jumlah penyesuaian tidak boleh 0.');
        }

        return DB::transaction(function () use ($variant, $delta, $note, $actorId, $type) {
            $locked = $this->lockVariant($variant->id);
            $newOnHand = $locked->stock_on_hand + $delta;

            if ($newOnHand < $locked->stock_reserved) {
                throw new RuntimeException(sprintf(
                    'Stok fisik tidak boleh lebih kecil dari stok tereservasi (%d).',
                    $locked->stock_reserved
                ));
            }

            if ($newOnHand < 0) {
                throw new RuntimeException('Stok fisik tidak boleh negatif.');
            }

            $locked->stock_on_hand = $newOnHand;
            $locked->save();

            ProductStockMovement::create([
                'clinic_id' => $locked->product->clinic_id,
                'clinic_product_id' => $locked->clinic_product_id,
                'product_variant_id' => $locked->id,
                'type' => $type,
                'quantity' => $delta,
                'stock_after' => $locked->stock_on_hand,
                'reserved_after' => $locked->stock_reserved,
                'reference_type' => null,
                'reference_id' => null,
                'note' => $note,
                'actor_id' => $actorId,
            ]);

            return $locked;
        });
    }

    private function lockVariant(?int $variantId): ProductVariant
    {
        if (!$variantId) {
            throw new RuntimeException('Varian produk tidak ditemukan pada item pesanan.');
        }

        $variant = ProductVariant::whereKey($variantId)->lockForUpdate()->first();

        if (!$variant) {
            throw new RuntimeException('Varian produk sudah tidak tersedia.');
        }

        return $variant;
    }

    private function logMovement(
        ProductVariant $variant,
        string $type,
        int $quantity,
        ProductOrder $order,
        ?string $note,
        ?int $actorId
    ): void {
        ProductStockMovement::create([
            'clinic_id' => $order->clinic_id,
            'clinic_product_id' => $variant->clinic_product_id,
            'product_variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $variant->stock_on_hand,
            'reserved_after' => $variant->stock_reserved,
            'reference_type' => ProductOrder::class,
            'reference_id' => $order->id,
            'note' => $note,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Ringkasan untuk dashboard: total unit tersedia pada satu klinik.
     */
    public function totalAvailableStock(int $clinicId): int
    {
        return (int) ProductVariant::query()
            ->whereHas('product', function ($query) use ($clinicId) {
                $query->where('clinic_id', $clinicId)->where('is_active', true);
            })
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_on_hand - stock_reserved), 0) as total')
            ->value('total');
    }

    /**
     * Stok rendah untuk peringatan dashboard.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductVariant>
     */
    public function lowStockVariants(int $clinicId, int $limit = 5)
    {
        return ProductVariant::with('product')
            ->whereHas('product', fn ($q) => $q->where('clinic_id', $clinicId)->where('is_active', true))
            ->where('is_active', true)
            ->whereColumn('stock_on_hand', '<=', 'low_stock_threshold')
            ->orderByRaw('(stock_on_hand - stock_reserved) asc')
            ->limit($limit)
            ->get();
    }
}
