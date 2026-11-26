<?php

namespace App\Models;

use App\Traits\HasPhotoUrl;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    use HasPhotoUrl;

    protected $fillable = [
        'user_id',
        'name',
        'microchip_number',
        'species',
        'species_code',
        'breed',
        'color',
        'markings',
        'age',
        'weight',
        'gender',
        'date_of_birth',
        'neutered_at',
        'blood_type',
        'photo',
        'notes',
    ];

    protected $casts = [
        'age'           => 'integer',
        'weight'        => 'float',
        'date_of_birth' => 'date:Y-m-d',
        'neutered_at'   => 'date:Y-m-d',
    ];

    protected $appends = ['photo_url'];

    /**
     * Get the user that owns the pet
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get bookings for this pet
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get medical records for this pet
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Alergi aktif hewan. Dipakai sebagai peringatan di form resep.
     */
    public function allergies()
    {
        return $this->hasMany(PetAllergy::class);
    }

    /**
     * Get weight records for this pet
     */
    public function weightRecords()
    {
        return $this->hasMany(\App\Models\WeightRecord::class);
    }

    /**
     * Get vaccination records for this pet
     */
    public function vaccinations()
    {
        return $this->hasMany(\App\Models\Vaccination::class);
    }
}
