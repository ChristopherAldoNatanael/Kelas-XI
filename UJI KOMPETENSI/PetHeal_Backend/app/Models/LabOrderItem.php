<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pemeriksaan yang benar-benar dipesan. Hasil laboratorium harus menunjuk
 * item ini, kecuali temuan tambahan yang disetujui dokter.
 */
class LabOrderItem extends Model
{
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_RESULTED = 'resulted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_COLLECTED,
        self::STATUS_RESULTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'clinic_id',
        'lab_order_id',
        'test_code',
        'test_name',
        'specimen_type',
        'status',
        'notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'lab_order_item_id');
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
