<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecordExport extends Model
{
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_JSON = 'json';
    public const FORMAT_CSV = 'csv';

    public const FORMATS = [self::FORMAT_PDF, self::FORMAT_JSON, self::FORMAT_CSV];

    public const PURPOSE_OWNER_COPY = 'owner_copy';
    public const PURPOSE_REFERRAL = 'referral';
    public const PURPOSE_INSURANCE = 'insurance';
    public const PURPOSE_LEGAL = 'legal';

    public const PURPOSES = [
        self::PURPOSE_OWNER_COPY,
        self::PURPOSE_REFERRAL,
        self::PURPOSE_INSURANCE,
        self::PURPOSE_LEGAL,
    ];

    protected $fillable = [
        'clinic_id', 'medical_record_id', 'version_id', 'format', 'purpose',
        'recipient_type', 'recipient_name', 'token_hash', 'token_used_at',
        'expires_at', 'downloaded_at', 'revoked_at', 'revoked_by',
        'access_count', 'last_accessed_at', 'last_accessed_ip',
        'file_hash', 'rendered_path', 'rendered_hash', 'content_hash',
        'signature_path', 'signature_hash', 'signed_at', 'signed_by',
        'created_by',
    ];

    protected $casts = [
        'token_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'signed_at' => 'datetime',
        'access_count' => 'integer',
    ];

    protected $hidden = ['token_hash'];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function version()
    {
        return $this->belongsTo(MedicalRecordVersion::class, 'version_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsed(): bool
    {
        return $this->token_used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function requiresSignature(): bool
    {
        return in_array($this->purpose, [self::PURPOSE_INSURANCE, self::PURPOSE_LEGAL], true);
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null && $this->signed_by !== null;
    }
}
