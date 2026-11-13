<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dokumen transaksi mutasi stok.
 *
 * Alur status:
 *   pending → processing → completed
 *   pending → cancelled
 *   processing → cancelled (stok belum berubah sama sekali)
 *
 * Stok hanya berubah di `completed`, tepat satu kali. Karena itu:
 *  - klik "proses" dua kali tidak memotong stok dua kali,
 *  - pembatalan sebelum selesai tidak perlu transaksi pembalik.
 */
class MedicineStockTransaction extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const TYPE_RETURN_SUPPLIER = 'return_supplier';
    public const TYPE_DISPOSAL = 'disposal';
    public const TYPE_QUARANTINE = 'quarantine';
    public const TYPE_DAMAGED = 'damaged';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_RETURN_SUPPLIER,
        self::TYPE_DISPOSAL,
        self::TYPE_QUARANTINE,
        self::TYPE_DAMAGED,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'reference',
        'clinic_id',
        'medicine_id',
        'batch_id',
        'type',
        'status',
        'quantity',
        'reason',
        'destination',
        'notes',
        'supplier_name',
        'return_date',
        'document_path',
        'destruction_date',
        'destruction_method',
        'witness_name',
        'minutes_path',
        'quarantine_location',
        'from_quarantine',
        'source_transaction_id',
        'direction',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'from_quarantine' => 'boolean',
        'return_date' => 'date:Y-m-d',
        'destruction_date' => 'date:Y-m-d',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }

    public function movements()
    {
        return $this->hasMany(MedicineStockMovement::class, 'transaction_id');
    }

    public function sourceTransaction()
    {
        return $this->belongsTo(self::class, 'source_transaction_id');
    }

    /** Tindak lanjut dari transaksi karantina ini (retur/pemusnahan/pembalik). */
    public function followUps()
    {
        return $this->hasMany(self::class, 'source_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function canProcess(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canComplete(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function canCancel(): bool
    {
        return $this->isOpen();
    }

    /** Karantina selesai tapi belum seluruhnya diretur/dimusnahkan. */
    public function needsFollowUp(): bool
    {
        return $this->type === self::TYPE_QUARANTINE
            && $this->status === self::STATUS_COMPLETED
            && $this->remainingFollowUpQuantity() > 0.0001;
    }

    public function resolvedFollowUpQuantity(): float
    {
        return (float) $this->followUps()
            ->where('status', self::STATUS_COMPLETED)
            ->sum('quantity');
    }

    public function remainingFollowUpQuantity(): float
    {
        return max(0, (float) $this->quantity - $this->resolvedFollowUpQuantity());
    }

    public function isReversed(): bool
    {
        return $this->followUps()
            ->where('status', self::STATUS_COMPLETED)
            ->where('direction', 'reverse')
            ->exists();
    }

    public function canReverse(): bool
    {
        return $this->type === self::TYPE_QUARANTINE
            && $this->status === self::STATUS_COMPLETED
            && ! $this->isReversed();
    }

    public function typeLabel(): string
    {
        return __('inventory.type_' . $this->type);
    }

    public function statusLabel(): string
    {
        return __('inventory.status_' . $this->status);
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'primary',
            self::STATUS_PROCESSING => 'sky',
            self::STATUS_CANCELLED => 'slate',
            default => 'amber',
        };
    }

    public function typeTone(): string
    {
        return match ($this->type) {
            self::TYPE_RETURN_SUPPLIER => 'blue',
            self::TYPE_DISPOSAL => 'rose',
            self::TYPE_QUARANTINE => 'amber',
            self::TYPE_DAMAGED => 'orange',
            default => 'violet',
        };
    }

    public function scopeForClinic(Builder $query, ?int $clinicId): Builder
    {
        return $clinicId ? $query->where('clinic_id', $clinicId) : $query;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference', 'like', '%' . $term . '%')
                ->orWhere('reason', 'like', '%' . $term . '%')
                ->orWhere('notes', 'like', '%' . $term . '%')
                ->orWhereHas('medicine', fn (Builder $m) => $m->where('name', 'like', '%' . $term . '%'));
        });
    }
}
