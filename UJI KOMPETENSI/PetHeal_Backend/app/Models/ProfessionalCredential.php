<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalCredential extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_PENDING = 'pending';

    public const TYPE_STR = 'STR';
    public const TYPE_SIP = 'SIP';
    public const TYPE_SIPP = 'SIPP';
    public const TYPE_SIPP_KESWAN = 'SIPP_KESWAN';
    public const TYPE_SIPP_INSEMINATOR = 'SIPP_INSEMINATOR';
    public const TYPE_SIPP_PKB = 'SIPP_PKB';
    public const TYPE_SIPP_ATR = 'SIPP_ATR';

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'paramedic_id',
        'credential_type',
        'number',
        'issued_at',
        'expires_at',
        'practice_scope',
        'status',
        'verified_at',
        'verified_by',
        'document_path',
        'is_current',
        'active_key',
        'superseded_at',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
        'superseded_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function paramedic(): BelongsTo
    {
        return $this->belongsTo(Paramedic::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(today());
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }
}
