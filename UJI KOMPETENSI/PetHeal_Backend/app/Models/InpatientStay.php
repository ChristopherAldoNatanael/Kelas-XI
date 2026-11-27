<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InpatientStay extends Model
{
    public const STATUS_ADMITTED = 'admitted';
    public const STATUS_IN_TREATMENT = 'in_treatment';
    public const STATUS_DISCHARGED = 'discharged';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_DIED = 'died';

    public const STATUSES = [
        self::STATUS_ADMITTED,
        self::STATUS_IN_TREATMENT,
        self::STATUS_DISCHARGED,
        self::STATUS_TRANSFERRED,
        self::STATUS_DIED,
    ];

    protected $fillable = [
        'clinic_id', 'encounter_id', 'medical_record_id', 'admitted_at',
        'discharged_at', 'kennel_code', 'ward', 'status',
        'admitting_doctor_id', 'discharge_summary', 'discharge_instructions',
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function encounter()
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function admittingDoctor()
    {
        return $this->belongsTo(Doctor::class, 'admitting_doctor_id');
    }

    public function locationLogs()
    {
        return $this->hasMany(InpatientLocationLog::class)->orderBy('moved_at');
    }

    public function medicationAdministrations()
    {
        return $this->hasMany(MedicationAdministration::class);
    }
}
