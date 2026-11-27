<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    public const DIRECTION_OUTGOING = 'outgoing';
    public const DIRECTION_INCOMING = 'incoming';

    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'medical_record_id',
        'clinic_id',
        'direction',
        'referral_number',
        'referred_to',
        'external_facility',
        'reason',
        'notes',
        'summary',
        'document_hash',
        'status',
        'referred_at',
        'returned_at',
        'result_notes',
        'verified_by',
        'verified_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'referred_at' => 'datetime',
        'returned_at' => 'datetime',
        'verified_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }
}
