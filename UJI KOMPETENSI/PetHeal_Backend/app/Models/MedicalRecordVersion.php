<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot immutable. Tidak ada update/delete dari aplikasi.
 */
class MedicalRecordVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'clinic_id',
        'medical_record_id',
        'version_no',
        'snapshot',
        'content_hash',
        'legacy_content_hash',
        'finalized_at',
        'finalized_by',
        'amendment_id',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'finalized_at' => 'datetime',
        'created_at' => 'datetime',
        'version_no' => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(MedicalRecordAmendment::class, 'amendment_id');
    }
}
