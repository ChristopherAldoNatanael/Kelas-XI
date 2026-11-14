<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClinicProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'brand',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        $path = preg_replace('#^(storage|public)/#', '', ltrim($this->image_path, '/'));

        return asset('storage/' . $path);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    /**
     * Produk yang benar-benar bisa dibeli: aktif, klinik aktif, dan punya
     * minimal satu varian aktif dengan stok tersedia > 0.
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->active()
            ->whereHas('clinic', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('variants', fn (Builder $q) => $q->active()->whereRaw('stock_on_hand > stock_reserved'));
    }

    public static function makeUniqueSlug(int $clinicId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('clinic_id', $clinicId)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    public function availableStock(): int
    {
        return $this->variants->sum(fn (ProductVariant $variant) => $variant->availableStock());
    }
}
