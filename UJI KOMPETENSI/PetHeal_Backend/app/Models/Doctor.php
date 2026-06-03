<?php

namespace App\Models;

use App\Traits\HasPhotoUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $specialization
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $photo
 * @property array|string|null $available_days
 * @property string|null $start_time
 * @property string|null $end_time
 * @property bool $is_active
 * @property string|null $available_time
 * @property string|null $photo_url
 */
class Doctor extends Model
{
    use HasPhotoUrl;

    protected $fillable = [
        'name',
        'specialization',
        'phone',
        'email',
        'photo',
        'available_days',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'available_days' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = ['available_time', 'photo_url', 'average_rating'];

    /**
     * Return a human-readable time range string, e.g. "08:00 - 17:00"
     * This maps to the Android Doctor.availableTime field.
     */
    public function getAvailableTimeAttribute(): ?string
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time . ' - ' . $this->end_time;
        }
        return null;
    }

    /**
     * Override available_days in JSON to return a comma-separated string
     * so Android's Doctor.availableDays: String? works correctly.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        // Normalise available_days → comma-separated string for Android
        if (isset($array['available_days']) && is_array($array['available_days'])) {
            $array['available_days'] = implode(', ', array_map('ucfirst', $array['available_days']));
        }
        return $array;
    }

    /**
     * Get bookings for this doctor
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get medical records created by this doctor
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Get reviews for this doctor
     */
    public function reviews()
    {
        return $this->hasMany(DoctorReview::class);
    }

    /**
     * Get average rating
     */
    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->reviews()->avg('rating');
        return $avg ? round($avg, 1) : null;
    }
}
