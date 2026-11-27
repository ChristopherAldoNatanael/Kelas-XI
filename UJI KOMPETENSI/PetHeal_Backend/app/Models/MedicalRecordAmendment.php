<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordAmendment extends Model
{
    public const TYPE_CLINICAL = 'clinical';
    public const TYPE_ADMINISTRATIVE = 'administrative';

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'base_version_id',
        'amendment_type',
        'requested_by',
        'requested_at',
        'reason',
        'before',
        'after',
        'status',
        'pending_key',
        'reviewer_credential_snapshot',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'applied_version_id',
        'applied_at',
        'signature_path',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'reviewer_credential_snapshot' => 'array',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function baseVersion(): BelongsTo
    {
        return $this->belongsTo(MedicalRecordVersion::class, 'base_version_id');
    }

    public function appliedVersion(): BelongsTo
    {
        return $this->belongsTo(MedicalRecordVersion::class, 'applied_version_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }
}
