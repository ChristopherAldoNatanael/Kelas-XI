<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalTemplate extends Model
{
    protected $fillable = [
        'clinic_id', 'template_key', 'name', 'species', 'version',
        'schema', 'is_current', 'active_key', 'created_by',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_current' => 'boolean',
        'version' => 'integer',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
