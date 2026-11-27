<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penilaian kesejahteraan hewan memakai lima kebebasan (five freedoms).
 * Penilaian baru dibuat sebagai baris baru agar perubahan kondisi dapat
 * dibandingkan dari waktu ke waktu.
 */
class AnimalWelfareAssessment extends Model
{
    public const FIELDS = [
        'freedom_hunger_thirst',
        'freedom_discomfort',
        'freedom_pain_injury_disease',
        'freedom_fear_distress',
        'freedom_natural_behavior',
    ];

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'encounter_id',
        'freedom_hunger_thirst',
        'freedom_discomfort',
        'freedom_pain_injury_disease',
        'freedom_fear_distress',
        'freedom_natural_behavior',
        'overall_score',
        'notes',
        'assessed_by',
        'assessed_at',
    ];

    protected $casts = [
        'freedom_hunger_thirst' => 'integer',
        'freedom_discomfort' => 'integer',
        'freedom_pain_injury_disease' => 'integer',
        'freedom_fear_distress' => 'integer',
        'freedom_natural_behavior' => 'integer',
        'overall_score' => 'integer',
        'assessed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function averageScore(): float
    {
        return round($this->overall_score / count(self::FIELDS), 2);
    }
}
