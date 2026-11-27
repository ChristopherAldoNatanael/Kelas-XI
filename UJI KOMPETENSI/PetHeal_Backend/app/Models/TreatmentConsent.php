<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentConsent extends Model
{
    public const TYPE_TINDAKAN = 'tindakan';
    public const TYPE_ANESTESI = 'anestesi';
    public const TYPE_EUTHANASIA = 'euthanasia';
    public const TYPE_RAWAT_INAP = 'rawat_inap';
    public const TYPE_DARURAT = 'darurat';

    public const TYPES = [
        self::TYPE_TINDAKAN,
        self::TYPE_ANESTESI,
        self::TYPE_EUTHANASIA,
        self::TYPE_RAWAT_INAP,
        self::TYPE_DARURAT,
    ];

    public const EMERGENCY_BASES = [
        'life_threatening',
        'owner_unavailable',
        'legal_obligation',
        'other',
    ];

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'pet_id',
        'user_id',
        'consent_type',
        'template_key',
        'template_version',
        'consent_text_hash',
        'risks_explained',
        'signer_name',
        'signer_relation',
        'guardian_name',
        'guardian_relation',
        'signature_path',
        'evidence_path',
        'evidence_hash',
        'signed_at',
        'witness_name',
        'ip_address',
        'emergency_basis',
        'revoked_at',
        'revoked_reason',
        'revoked_by',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function revokedBy()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isEmergency(): bool
    {
        return $this->emergency_basis !== null;
    }
}
