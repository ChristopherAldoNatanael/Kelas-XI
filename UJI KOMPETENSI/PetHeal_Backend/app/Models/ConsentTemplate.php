<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template teks persetujuan tindakan. Template tidak diedit di tempat:
 * perubahan membuat versi baru dan menonaktifkan versi lama.
 */
class ConsentTemplate extends Model
{
    protected $fillable = [
        'clinic_id',
        'template_key',
        'name',
        'consent_type',
        'version',
        'body',
        'body_hash',
        'is_current',
        'active_key',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_current' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
