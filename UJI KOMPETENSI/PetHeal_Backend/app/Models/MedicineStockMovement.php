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

    /**
     * `quantity` dan `stock_after` WAJIB fillable: keduanya ditulis lewat
     * create() saat mutasi dicatat. Tanpa keduanya, mutasi tersimpan dengan
     * nilai default 0 dan saldo stok tidak pernah bertambah.
     */
    protected $fillable = [
        'medicine_id',
        'clinic_id',
        'type',
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
            default => ucfirst($this->type),
        };
    }
}
