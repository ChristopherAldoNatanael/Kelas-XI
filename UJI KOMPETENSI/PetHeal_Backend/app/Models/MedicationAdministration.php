<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationAdministration extends Model
{
    public const SOURCE_PRESCRIPTION = 'prescription';
    public const SOURCE_VERBAL_ORDER = 'verbal_order';
    public const SOURCE_EMERGENCY = 'emergency';

    public const SOURCES = [self::SOURCE_PRESCRIPTION, self::SOURCE_VERBAL_ORDER, self::SOURCE_EMERGENCY];

    public const STATUS_GIVEN = 'given';
    public const STATUS_REFUSED = 'refused';
    public const STATUS_MISSED = 'missed';
    public const STATUS_HELD = 'held';

    public const STATUSES = [self::STATUS_GIVEN, self::STATUS_REFUSED, self::STATUS_MISSED, self::STATUS_HELD];

    protected $fillable = [
        'clinic_id', 'inpatient_stay_id', 'prescription_item_id', 'order_source',
        'ordered_by', 'medicine_id', 'batch_id', 'dose_value', 'dose_unit',
        'route', 'administered_at', 'administered_by', 'status',
        'idempotency_key', 'notes',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'dose_value' => 'decimal:3',
    ];

    public function stay()
    {
        return $this->belongsTo(InpatientStay::class, 'inpatient_stay_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function prescriptionItem()
    {
        return $this->belongsTo(PrescriptionItem::class);
    }
}
