<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master obat milik satu klinik.
 *
 * `stock` adalah saldo. JANGAN pernah menulis `stock` langsung dari controller
 * atau form — gunakan `MedicineStockService` agar setiap perubahan punya baris
 * mutasi yang bisa diaudit.
 */
class Medicine extends Model
{
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
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'price' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function movements()
    {
        return $this->hasMany(MedicineStockMovement::class);
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

    public function scopeForClinic($query, ?int $clinicId)
    {
        return $clinicId ? $query->where('clinic_id', $clinicId) : $query;
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }
}
