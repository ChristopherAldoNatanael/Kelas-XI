<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifiableDiseaseReport extends Model
{
    public const CLASSIFICATION_SUSPECTED = 'suspected';
    public const CLASSIFICATION_PROBABLE = 'probable';
    public const CLASSIFICATION_CONFIRMED = 'confirmed';

    public const CLASSIFICATIONS = [
        self::CLASSIFICATION_SUSPECTED,
        self::CLASSIFICATION_PROBABLE,
        self::CLASSIFICATION_CONFIRMED,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_REPORTED = 'reported';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY,
        self::STATUS_REPORTED,
        self::STATUS_VERIFIED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'clinic_id', 'medical_record_id', 'encounter_id',
        'disease_code', 'disease_name', 'classification',
        'indicated_at', 'report_deadline_at', 'status',
        'reported_at', 'escalated_at', 'reported_by',
        'isikhnas_reference', 'evidence_path', 'verified_at', 'verified_by',
        'cancel_reason', 'cancelled_at', 'cancelled_by', 'notes',
    ];

    protected $casts = [
        'indicated_at' => 'datetime',
        'report_deadline_at' => 'datetime',
        'reported_at' => 'datetime',
        'escalated_at' => 'datetime',
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function encounter()
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isLate(): bool
    {
        if ($this->reported_at === null || $this->report_deadline_at === null) {
            return $this->report_deadline_at !== null && $this->report_deadline_at->isPast();
        }

        return $this->reported_at->greaterThan($this->report_deadline_at);
    }

    public function isOverdue(): bool
    {
        return $this->reported_at === null
            && $this->report_deadline_at !== null
            && $this->report_deadline_at->isPast()
            && ! in_array($this->status, [self::STATUS_REPORTED, self::STATUS_VERIFIED, self::STATUS_CANCELLED], true);
    }

    public function scopeOverdue($query)
    {
        return $query
            ->whereNull('reported_at')
            ->where('report_deadline_at', '<', now())
            ->whereNotIn('status', [self::STATUS_REPORTED, self::STATUS_VERIFIED, self::STATUS_CANCELLED]);
    }
}
