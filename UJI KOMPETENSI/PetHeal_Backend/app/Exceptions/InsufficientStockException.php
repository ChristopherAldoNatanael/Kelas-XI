<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Stok tidak cukup pada saat reservasi/komitmen.
 *
 * Dilempar di dalam transaksi database supaya seluruh pesanan dibatalkan —
 * tidak pernah ada order setengah jadi dengan stok negatif.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly string $variantName,
        public readonly int $requested,
        public readonly int $available
    ) {
        parent::__construct(sprintf(
            'Stok %s — %s tidak mencukupi. Diminta %d, tersedia %d.',
            $productName,
            $variantName,
            $requested,
            $available
        ));
    }
}
