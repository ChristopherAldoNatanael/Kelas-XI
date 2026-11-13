<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu penerimaan obat dengan nomor batch dan tanggal kedaluwarsa sendiri.
 *
 * Dua saldo dijaga terpisah:
 *  - `stock`             → tersedia, boleh dipakai untuk resep (bila belum ED).
 *  - `quarantined_stock` → sudah dipisahkan fisik, tidak boleh dipakai,
 *                          menunggu retur supplier atau pemusnahan.
 *
 * Total fisik batch = stock + quarantined_stock. Karantina TIDAK mengubah
 * total fisik; hanya memindahkan saldo antar dua kolom.
 */
class MedicineBatch extends Model
{
    protected $fillable = [
        'clinic_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'quarantined_stock' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
        'received_at' => 'date:Y-m-d',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function movements()
    {
        return $this->hasMany(MedicineStockMovement::class, 'batch_id');
    }

    public function transactions()
    {
        return $this->hasMany(MedicineStockTransaction::class, 'batch_id');
    }

    public function totalPhysicalStock(): float
    {
        return (float) $this->stock + (float) $this->quarantined_stock;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function expiresWithin(int $days): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->betweenIncluded(today(), today()->addDays($days));
    }

    public function expiryDaysLeft(): ?int
    {
        return $this->expiry_date === null
            ? null
            : (int) today()->diffInDays($this->expiry_date, false);
    }

    /**
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

    /** Boleh dipakai untuk resep: belum ED dan masih ada saldo tersedia. */
    public function isDispensable(): bool
    {
        return ! $this->isExpired() && (float) $this->stock > 0;
    }

    public function scopeForClinic(Builder $query, ?int $clinicId): Builder
    {
        return $clinicId ? $query->where('clinic_id', $clinicId) : $query;
    }

    public function scopeWithAvailableStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeWithQuarantineStock(Builder $query): Builder
    {
        return $query->where('quarantined_stock', '>', 0);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today());
    }

    public function scopeExpiringSoon(Builder $query, ?int $days = null): Builder
    {
        $days ??= medicine_expiry_warning_days();

        return $query->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', today());
        });
    }

    /** FEFO: yang paling cepat kedaluwarsa dipakai lebih dulu. */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    public function expiryDateLabel(): string
    {
        return $this->expiry_date?->format('d M Y') ?? '—';
    }

    /** Tanggal kedaluwarsa sebagai Carbon/string, aman untuk form/JS. */
    public function expiryDateValue(): ?string
    {
        return $this->expiry_date instanceof Carbon
            ? $this->expiry_date->format('Y-m-d')
            : ($this->expiry_date ? (string) $this->expiry_date : null);
    }
}
