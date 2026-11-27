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
        'prescription_id',
        'medicine_id',
        'medicine_name',
        'dose',
        'dose_value',
        'dose_unit',
        'dose_basis',
        'weight_at_prescription',
        'concentration',
        'calculated_dose',
        'dose_check_status',
        'dose_check_notes',
        'dosage_form',
        'strength',
        'route',
        'frequency',
        'duration_days',
        'instruction',
        'quantity',
        'total_quantity',
        'withdrawal_period',
        'dose_reference_id',
        'dose_reference_source',
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
        'total_quantity' => 'decimal:2',
        'dose_value' => 'decimal:4',
        'weight_at_prescription' => 'decimal:2',
        'calculated_dose' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'duration_days' => 'integer',
        'is_antibiotic' => 'boolean',
        'expiry_date' => 'date:Y-m-d',
        'dispensed_at' => 'datetime',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function doseReference()
    {
        return $this->belongsTo(DoseReference::class);
    }

    public function dispensationItems()
    {
        return $this->hasMany(DispensationItem::class);
    }

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
