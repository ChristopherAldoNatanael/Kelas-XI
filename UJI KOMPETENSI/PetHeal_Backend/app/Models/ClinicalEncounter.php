<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Episode klinis.
 *
 * `booking_id` adalah sumber kebenaran relasi booking. Rekam medis menunjuk
 * encounter lewat `medical_records.encounter_id`, bukan sebaliknya.
 */
class ClinicalEncounter extends Model
{
    public const TYPE_BOOKING = 'booking';
    public const TYPE_WALK_IN = 'walk_in';
    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_INPATIENT = 'inpatient';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPES = [
        self::TYPE_BOOKING,
        self::TYPE_WALK_IN,
        self::TYPE_EMERGENCY,
        self::TYPE_FOLLOW_UP,
        self::TYPE_INPATIENT,
    ];

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'clinic_id',
        'pet_id',
        'booking_id',
        'type',
        'status',
        'attending_doctor_id',
        'supervising_doctor_id',
        'chief_complaint',
        'triage_level',
        'opened_at',
        'closed_at',
        'disposition',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'attending_doctor_id');
    }

    public function supervisingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'supervising_doctor_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function notifiableDiseaseReports()
    {
        return $this->hasMany(NotifiableDiseaseReport::class, 'encounter_id');
    }

    public function inpatientStay()
    {
        return $this->hasOne(InpatientStay::class, 'encounter_id');
    }

    public function medicationAdministrations()
    {
        return $this->hasManyThrough(
            MedicationAdministration::class,
            InpatientStay::class,
            'encounter_id',
            'inpatient_stay_id',
            'id',
            'id'
        );
    }

    public function vitalMeasurements(): HasMany
    {
        return $this->hasMany(VitalMeasurement::class)->orderBy('measured_at');
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class)->orderBy('ordered_at');
    }

    public function welfareAssessments(): HasMany
    {
        return $this->hasMany(AnimalWelfareAssessment::class)->orderBy('assessed_at');
    }
}
