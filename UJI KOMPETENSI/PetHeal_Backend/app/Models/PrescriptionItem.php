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
        'route',
        'frequency',
        'duration_days',
        'instruction',
        'quantity',
        'unit',
        'batch_number',
        'expiry_date',
        'drug_class',
        'is_antibiotic',
        'dispensed_at',
        'unit_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'duration_days' => 'integer',
        'is_antibiotic' => 'boolean',
        'expiry_date' => 'date:Y-m-d',
        'dispensed_at' => 'datetime',
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
