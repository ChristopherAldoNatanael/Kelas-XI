<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOrderItem extends Model
{
    protected $fillable = [
        'product_order_id',
        'clinic_product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'image_path',
        'unit_price',
        'discount_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'discount_price' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClinicProduct::class, 'clinic_product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
