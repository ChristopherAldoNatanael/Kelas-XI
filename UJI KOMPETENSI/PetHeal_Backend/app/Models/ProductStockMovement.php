<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockMovement extends Model
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RESERVATION = 'reservation';
    public const TYPE_RELEASE = 'release';
    public const TYPE_SALE = 'sale';
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'clinic_id',
        'clinic_product_id',
        'product_variant_id',
        'type',
        'quantity',
        'stock_after',
        'reserved_after',
        'reference_type',
        'reference_id',
        'note',
        'actor_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_after' => 'integer',
        'reserved_after' => 'integer',
        'reference_id' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClinicProduct::class, 'clinic_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
