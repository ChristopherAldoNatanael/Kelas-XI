<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rentang rujukan laboratorium milik satu klinik.
 *
 * Bila tidak ada baris yang cocok, sistem tidak boleh memakai rentang generik
 * atau menampilkan nilai normal palsu.
 */
class LabReferenceRange extends Model
{
    protected $fillable = [
        'clinic_id',
        'species',
        'breed',
        'sex',
        'age_min_days',
        'age_max_days',
        'test_code',
        'parameter',
        'unit',
        'reference_low',
        'reference_high',
        'reference_text',
        'method',
        'analyzer',
        'specimen_type',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'reference_low' => 'decimal:4',
        'reference_high' => 'decimal:4',
        'age_min_days' => 'integer',
        'age_max_days' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }
}
