<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris buku besar stok obat. `quantity` bertanda:
 * masuk/retur = positif, keluar = negatif, koreksi = selisih (bisa keduanya).
 */
class MedicineStockMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RETURN = 'return';
    /** Pemusnahan obat kedaluwarsa / rusak. Quantity selalu negatif. */
    public const TYPE_DISPOSAL = 'disposal';
    /** Retur ke supplier (stok keluar permanen). */
    public const TYPE_RETURN_SUPPLIER = 'return_supplier';
    /** Pemisahan stok ke karantina (available → quarantine). */
    public const TYPE_QUARANTINE = 'quarantine';
    /** Obat rusak yang langsung dikeluarkan dari stok tersedia. */
    public const TYPE_DAMAGED = 'damaged';

    public const STOCK_STATE_AVAILABLE = 'available';
    public const STOCK_STATE_QUARANTINE = 'quarantine';

    /**
     * `quantity` dan `stock_after` WAJIB fillable: keduanya ditulis lewat
     * create() saat mutasi dicatat. Tanpa keduanya, mutasi tersimpan dengan
     * nilai default 0 dan saldo stok tidak pernah bertambah.
     */
    protected $fillable = [
        'transaction_id',
        'medicine_id',
        'batch_id',
        'clinic_id',
        'type',
        'stock_state',
        'quantity',
        'stock_after',
        'reason',
        'medical_record_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'stock_after' => 'decimal:2',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }

    public function transaction()
    {
        return $this->belongsTo(MedicineStockTransaction::class, 'transaction_id');
    }

    public function isQuarantineMovement(): bool
    {
        return $this->stock_state === self::STOCK_STATE_QUARANTINE;
    }

    public function stockStateLabel(): string
    {
        return $this->isQuarantineMovement()
            ? __('inventory.stock_state_quarantine')
            : __('inventory.stock_state_available');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Label siap tampil untuk tabel mutasi. */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_IN => 'Stok masuk',
            self::TYPE_OUT => 'Pemakaian',
            self::TYPE_RETURN => 'Pengembalian',
            self::TYPE_ADJUSTMENT => 'Koreksi',
            self::TYPE_DISPOSAL => 'Pemusnahan',
            self::TYPE_RETURN_SUPPLIER => 'Retur supplier',
            self::TYPE_QUARANTINE => 'Karantina',
            self::TYPE_DAMAGED => 'Obat rusak',
            default => ucfirst($this->type),
        };
    }
}
