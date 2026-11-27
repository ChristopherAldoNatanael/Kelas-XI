<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hasil laboratorium terstruktur (satu baris per parameter) supaya nilai dan
 * rentang rujukan bisa dibandingkan antar kunjungan, bukan hanya PDF lampiran.
 */
class LabResult extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PRELIMINARY = 'preliminary';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LEGACY = 'legacy';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PRELIMINARY,
        self::STATUS_VERIFIED,
        self::STATUS_CORRECTED,
        self::STATUS_CANCELLED,
        self::STATUS_LEGACY,
    ];

    public const REFERENCE_MATCHED = 'matched';
    public const REFERENCE_UNAVAILABLE = 'reference_unavailable';
    public const REFERENCE_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'lab_order_id',
        'lab_order_item_id',
        'lab_specimen_id',
        'test_name',
        'parameter',
        'species',
        'value',
        'unit',
        'reference_range',
        'reference_low',
        'reference_high',
        'reference_text',
        'reference_status',
        'reference_range_id',
        'flag',
        'status',
        'method',
        'collected_at',
        'resulted_at',
        'performed_at',
        'verified_at',
        'verified_by',
        'is_additional',
        'additional_approved_by',
        'correction_of_id',
        'correction_reason',
        'void_reason',
        'voided_at',
        'voided_by',
        'notes',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'collected_at' => 'datetime',
        'resulted_at' => 'datetime',
        'verified_at' => 'datetime',
        'voided_at' => 'datetime',
        'reference_low' => 'decimal:4',
        'reference_high' => 'decimal:4',
        'is_additional' => 'boolean',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function order()
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(LabOrderItem::class, 'lab_order_item_id');
    }

    public function specimen()
    {
        return $this->belongsTo(LabSpecimen::class, 'lab_specimen_id');
    }

    public function referenceRange()
    {
        return $this->belongsTo(LabReferenceRange::class, 'reference_range_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function correctionOf()
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function isVerified(): bool
    {
        return in_array($this->status, [self::STATUS_VERIFIED, self::STATUS_CORRECTED], true);
    }

    public function isLegacy(): bool
    {
        return $this->status === self::STATUS_LEGACY;
    }
}
