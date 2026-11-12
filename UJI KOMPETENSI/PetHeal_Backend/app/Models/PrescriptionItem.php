<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris resep pada rekam medis.
 *
 * `medicine_name`, `unit`, dan `unit_price` adalah salinan saat resep ditulis.
 * Kalau master obat berubah kemudian, resep lama tidak ikut berubah.
 */
class PrescriptionItem extends Model
{
    protected $fillable = [
        'medical_record_id',
        'medicine_id',
        'medicine_name',
        'dose',
        'quantity',
        'unit',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /** Subtotal harga baris resep. */
    public function getSubtotalAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
