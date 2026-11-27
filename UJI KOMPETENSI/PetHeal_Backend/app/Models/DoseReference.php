<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoseReference extends Model
{
    protected $fillable = [
        'clinic_id',
        'species',
        'medicine_name',
        'drug_class',
        'indication',
        'route',
        'dose_min',
        'dose_max',
        'dose_unit',
        'dose_basis',
        'max_dose',
        'source',
        'source_version',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'dose_min' => 'decimal:4',
        'dose_max' => 'decimal:4',
        'max_dose' => 'decimal:4',
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
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', today());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', today());
            });
    }
}
