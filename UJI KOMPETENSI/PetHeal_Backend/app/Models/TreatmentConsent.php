<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentConsent extends Model
{
    protected $fillable = [
        'medical_record_id',
        'pet_id',
        'user_id',
        'consent_type',
        'risks_explained',
        'signer_name',
        'signer_relation',
        'signature_path',
        'signed_at',
        'witness_name',
        'ip_address',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }
}
