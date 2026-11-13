<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master obat milik satu klinik.
 *
 * `stock` adalah saldo. JANGAN pernah menulis `stock` langsung dari controller
 * atau form — gunakan `MedicineStockService` agar setiap perubahan punya baris
 * mutasi yang bisa diaudit.
 *
 * Siklus hidup obat kedaluwarsa:
 *  1. `expiresWithin(60)` → tandai "hampir kedaluwarsa", pakai lebih dulu (FEFO).
 *  2. `isExpired()` → blokir dari resep, wajib dimusnahkan.
 *  3. `dispose()` di `MedicineStockService` → stok 0, mutasi `disposal`, obat nonaktif.
 */
class Medicine extends Model
{
    public const DISPOSAL_DESTROYED = 'destroyed';
    public const DISPOSAL_RETURNED = 'returned_supplier';

    public const DISPOSAL_METHODS = [
        self::DISPOSAL_DESTROYED,
        self::DISPOSAL_RETURNED,
    ];

    protected $fillable = [
        'clinic_id',
        'name',
        'unit',
        'form',
        'min_stock',
        'price',
        'expiry_date',
        'manufacturer',
        'notes',
        'is_active',
        'disposed_at',
        'disposal_method',
        'disposal_reason',
        'disposed_quantity',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'quarantined_stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'price' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'disposed_at' => 'datetime',
        'disposed_quantity' => 'decimal:2',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function movements()
    {
        return $this->hasMany(MedicineStockMovement::class);
    }

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function stockTransactions()
    {
        return $this->hasMany(MedicineStockTransaction::class);
    }

    /** Total stok fisik = tersedia + karantina. */
    public function physicalStock(): float
    {
        return (float) $this->stock + (float) $this->quarantined_stock;
    }

    /**
     * Stok yang benar-benar boleh dipakai untuk resep: batch belum ED dan
     * masih punya saldo tersedia. Bila obat belum punya batch sama sekali
     * (data sangat lama), pakai saldo obat sebagai fallback.
     */
    public function dispensableStock(): float
    {
        if ($this->relationLoaded('batches')) {
            return (float) $this->batches
                ->filter(fn (MedicineBatch $batch) => $batch->isDispensable())
                ->sum('stock');
        }

        if (! $this->batches()->exists()) {
            return $this->isExpired() ? 0.0 : (float) $this->stock;
        }

        return (float) $this->batches()
            ->withAvailableStock()
            ->notExpired()
            ->sum('stock');
    }

    /** Stok di bawah atau sama dengan ambang minimum. */
    public function isLowStock(): bool
    {
        return (float) $this->stock <= (float) $this->min_stock;
    }

    /** Sudah lewat tanggal kedaluwarsa. */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /** Kedaluwarsa dalam N hari ke depan (termasuk hari ini). */
    public function expiresWithin(int $days = 30): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->between(today(), today()->addDays($days));
    }

    /** Stok sudah dimusnahkan/diretur ke supplier. */
    public function isDisposed(): bool
    {
        return $this->disposed_at !== null;
    }

    /**
     * Sisa hari menuju kedaluwarsa. Negatif = sudah lewat.
     * Null = obat tidak punya tanggal kedaluwarsa.
     */
    public function expiryDaysLeft(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) today()->diffInDays($this->expiry_date, false);
    }

    /**
     * Status kedaluwarsa untuk badge di UI.
     * expired | expiring | safe | none
     */
    public function expiryStatus(): string
    {
        if ($this->expiry_date === null) {
            return 'none';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return $this->expiresWithin(medicine_expiry_warning_days())
            ? 'expiring'
            : 'safe';
    }

    /** Obat boleh diresepkan: aktif, ada stok layak pakai, dan belum kedaluwarsa. */
    public function isPrescribable(): bool
    {
        return $this->is_active && $this->dispensableStock() > 0;
    }

    public function getDisposalMethodLabelAttribute(): ?string
    {
        return match ($this->disposal_method) {
            self::DISPOSAL_DESTROYED => 'Dimusnahkan',
            self::DISPOSAL_RETURNED => 'Retur ke supplier',
            default => null,
        };
    }

    public function scopeForClinic($query, ?int $clinicId)
    {
        return $clinicId ? $query->where('clinic_id', $clinicId) : $query;
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeExpiringSoon($query, ?int $days = null)
    {
        $days ??= medicine_expiry_warning_days();

        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    /**
     * Obat yang punya stok tersedia pada batch yang belum kedaluwarsa.
     * Dipakai form resep: obat dengan satu batch ED tapi batch lain masih
     * layak harus tetap bisa diresepkan (FEFO memilih batch yang layak).
     */
    public function scopeDispensable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('batches', function (Builder $q) {
                $q->withAvailableStock()->notExpired();
            });
    }

    /** Sudah kedaluwarsa (tanggal lewat). */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today());
    }

    /** Belum kedaluwarsa ATAU tidak punya tanggal kedaluwarsa. */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', today());
        });
    }

    public function scopeDisposed(Builder $query): Builder
    {
        return $query->whereNotNull('disposed_at');
    }

    public function scopeNotDisposed(Builder $query): Builder
    {
        return $query->whereNull('disposed_at');
    }

    public function scopeHasQuarantineStock(Builder $query): Builder
    {
        return $query->where('quarantined_stock', '>', 0);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Urutan FEFO (First Expired First Out): obat paling cepat kedaluwarsa
     * dipakai lebih dulu. Obat tanpa tanggal kedaluwarsa ditaruh paling akhir.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('name');
    }
}
