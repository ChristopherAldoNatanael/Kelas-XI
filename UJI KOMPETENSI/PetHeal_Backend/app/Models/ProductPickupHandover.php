<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat serah terima barang. Satu pesanan hanya punya satu baris (unique),
 * sehingga dua petugas yang menekan konfirmasi bersamaan tidak bisa
 * menghasilkan dua penyerahan.
 */
class ProductPickupHandover extends Model
{
    protected $fillable = [
        'product_order_id',
        'product_pickup_pass_id',
        'clinic_id',
        'user_id',
        'handed_over_by',
        'items_snapshot',
        'verification_method',
        'handed_over_at',
    ];

    protected $casts = [
        'items_snapshot' => 'array',
        'handed_over_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(ProductPickupPass::class, 'product_pickup_pass_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }
}
