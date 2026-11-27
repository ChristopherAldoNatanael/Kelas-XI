<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispensationItem extends Model
{
    protected $fillable = [
        'clinic_id',
        'dispensation_id',
        'prescription_item_id',
        'medicine_id',
        'batch_id',
        'quantity',
        'unit',
        'medicine_name',
        'batch_number',
        'expiry_date',
        'unit_price',
        'stock_movement_id',
        'reversal_of_item_id',
        'original_batch_id',
        'reversed_quantity',
        'registration_status_snapshot',
        'eligibility_override_reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'reversed_quantity' => 'decimal:2',
        'expiry_date' => 'date:Y-m-d',
    ];

    public function dispensation(): BelongsTo
    {
        return $this->belongsTo(Dispensation::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(MedicineStockMovement::class, 'stock_movement_id');
    }
}
