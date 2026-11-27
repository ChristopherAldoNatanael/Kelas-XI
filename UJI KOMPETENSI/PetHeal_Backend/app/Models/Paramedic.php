<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paramedic extends Model
{
    protected $fillable = [
        'clinic_id',
        'user_id',
        'name',
        'supervising_doctor_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'supervising_doctor_id');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ProfessionalCredential::class);
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(ParamedicAuthorization::class);
    }
}
