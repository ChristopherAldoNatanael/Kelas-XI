<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'clinic_product_id',
        'sku',
        'name',
        'price',
        'discount_price',
        'stock_on_hand',
        'stock_reserved',
        'low_stock_threshold',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'discount_price' => 'integer',
        'stock_on_hand' => 'integer',
        'stock_reserved' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClinicProduct::class, 'clinic_product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function availableStock(): int
    {
        return max(0, $this->stock_on_hand - $this->stock_reserved);
    }

    public function effectivePrice(): int
    {
        return $this->discount_price !== null && $this->discount_price < $this->price
            ? (int) $this->discount_price
            : (int) $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->discount_price !== null && $this->discount_price < $this->price;
    }

    public function isLowStock(): bool
    {
        return $this->availableStock() <= (int) $this->low_stock_threshold;
    }
}
