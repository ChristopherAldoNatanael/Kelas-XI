<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pengukuran tanda vital. Banyak baris boleh menunjuk encounter yang sama
 * agar perubahan kondisi pasien dapat dipantau dari waktu ke waktu.
 *
 * Baris yang sudah tercatat tidak boleh diedit; koreksi dilakukan dengan
 * membuat pengukuran baru atau menandai baris lama sebagai `void`.
 */
class VitalMeasurement extends Model
{
    public const STATUS_RECORDED = 'recorded';
    public const STATUS_VOID = 'void';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_LEGACY = 'legacy';
    public const SOURCE_DEVICE = 'device';

    public const SHIFT_DAY = 'pagi';
    public const SHIFT_EVENING = 'siang';
    public const SHIFT_NIGHT = 'malam';

    public const SHIFTS = [
        self::SHIFT_DAY,
        self::SHIFT_EVENING,
        self::SHIFT_NIGHT,
    ];

    protected $fillable = [
        'clinic_id',
        'encounter_id',
        'medical_record_id',
        'pet_id',
        'legacy_vital_id',
        'temperature_c',
        'heart_rate',
        'respiratory_rate',
        'crt_seconds',
        'mucosa',
        'hydration',
        'bcs',
        'pain_score',
        'weight_kg',
        'notes',
        'measured_at',
        'source',
        'shift',
        'status',
        'recorded_by',
        'void_reason',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'temperature_c' => 'decimal:1',
        'crt_seconds' => 'decimal:1',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'bcs' => 'integer',
        'pain_score' => 'integer',
        'weight_kg' => 'decimal:2',
        'measured_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeRecorded($query)
    {
        return $query->where('status', self::STATUS_RECORDED);
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function toSeriesArray(): array
    {
        return [
            'id' => $this->id,
            'measured_at' => $this->measured_at?->toDateTimeString(),
            'shift' => $this->shift,
            'source' => $this->source,
            'temperature_c' => $this->temperature_c,
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'crt_seconds' => $this->crt_seconds,
            'mucosa' => $this->mucosa,
            'hydration' => $this->hydration,
            'bcs' => $this->bcs,
            'pain_score' => $this->pain_score,
            'weight_kg' => $this->weight_kg,
            'notes' => $this->notes,
        ];
    }
}
