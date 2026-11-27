<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordCredentialSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'version_id',
        'holder_type',
        'holder_id',
        'credential_type',
        'number',
        'expires_at',
        'practice_scope',
        'credential_status',
        'verified_at',
        'verified_by',
        'snapshot',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'verified_at' => 'datetime',
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MedicalRecordVersion::class, 'version_id');
    }
}
