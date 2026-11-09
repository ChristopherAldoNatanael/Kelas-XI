<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Clinic extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'email',
        'logo_path',
        'primary_color',
        'description',
        'is_active',
        'latitude',
        'longitude',
        'rating',
        'reviews_count',
        'opening_time',
        'closing_time',
        'operating_days',
        'website',
        'featured_image_path',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'latitude'        => 'decimal:7',
        'longitude'       => 'decimal:7',
        'rating'          => 'decimal:2',
        'reviews_count'   => 'integer',
        'operating_days'  => 'array',
    ];

    protected $appends = ['logo_url', 'featured_image_url'];

    /**
     * Get the full URL for the clinic logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        if (filter_var($this->logo_path, FILTER_VALIDATE_URL)) {
            return $this->logo_path;
        }

        $path = ltrim($this->logo_path, '/');
        $path = preg_replace('#^storage/#', '', $path);
        $path = preg_replace('#^public/#', '', $path);

        return asset('storage/' . $path);
    }

    /**
     * Get the full URL for the clinic featured image.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (!$this->featured_image_path) {
            return null;
        }

        if (filter_var($this->featured_image_path, FILTER_VALIDATE_URL)) {
            return $this->featured_image_path;
        }

        $path = ltrim($this->featured_image_path, '/');
        $path = preg_replace('#^storage/#', '', $path);
        $path = preg_replace('#^public/#', '', $path);

        return asset('storage/' . $path);
    }

    /**
     * Check if clinic is currently open based on operating_days and hours.
     * Returns null if no schedule info available.
     */
    public function isOpenNow(): ?bool
    {
        if (!$this->opening_time || !$this->closing_time) {
            return null;
        }

        $now = Carbon::now('Asia/Jakarta');
        $dayOfWeek = strtolower($now->format('l')); // 'monday', 'tuesday', etc.

        $days = $this->operating_days;
        if (!empty($days) && !in_array('all', $days) && !in_array($dayOfWeek, $days)) {
            return false;
        }

        $opening = Carbon::parse($this->opening_time, 'Asia/Jakarta');
        $closing = Carbon::parse($this->closing_time, 'Asia/Jakarta');
        $currentTime = Carbon::parse($now->format('H:i'), 'Asia/Jakarta');

        return $currentTime->between($opening, $closing);
    }

    /**
     * Calculate distance in km from given lat/lng using Haversine formula.
     * Returns null if clinic has no coordinates.
     */
    public function distanceFrom(float $lat, float $lng): ?float
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        $earthRadius = 6371; // km

        $latFrom = deg2rad((float) $this->latitude);
        $lngFrom = deg2rad((float) $this->longitude);
        $latTo = deg2rad($lat);
        $lngTo = deg2rad($lng);

        $latDiff = $latTo - $latFrom;
        $lngDiff = $lngTo - $lngFrom;

        $a = sin($latDiff / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDiff / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        return round($earthRadius * $c, 1);
    }

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function joinRequests()
    {
        return $this->hasMany(ClinicJoinRequest::class);
    }

    public function scheduleExceptions()
    {
        return $this->hasMany(DoctorScheduleException::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
