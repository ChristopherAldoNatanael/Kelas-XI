<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order pemeriksaan laboratorium. Status order terpisah dari status item,
 * spesimen, dan hasil agar tidak ada satu kolom yang menanggung banyak makna.
 */
class LabOrder extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIALLY_COLLECTED = 'partially_collected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ORDERED,
        self::STATUS_PARTIALLY_COLLECTED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_ROUTINE = 'routine';
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_STAT = 'stat';

    public const PRIORITIES = [
        self::PRIORITY_ROUTINE,
        self::PRIORITY_URGENT,
        self::PRIORITY_STAT,
    ];

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'encounter_id',
        'ordered_by',
        'ordered_at',
        'priority',
        'status',
        'clinical_notes',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(LabSpecimen::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
