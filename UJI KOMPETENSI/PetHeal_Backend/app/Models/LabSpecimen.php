<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Spesimen fisik untuk satu order. Satu order dapat memiliki beberapa
 * spesimen; status spesimen tidak boleh disamakan dengan status hasil.
 */
class LabSpecimen extends Model
{
    public const STATUS_PENDING = 'pending_collection';
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSED = 'processed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COLLECTED,
        self::STATUS_RECEIVED,
        self::STATUS_REJECTED,
        self::STATUS_PROCESSED,
    ];

    protected $fillable = [
        'clinic_id',
        'lab_order_id',
        'accession_number',
        'specimen_type',
        'collected_at',
        'collected_by',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'lab_specimen_id');
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
