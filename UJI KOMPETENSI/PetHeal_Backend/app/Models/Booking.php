<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'pet_id',
        'doctor_id',
        'service_id',
        'booking_date',
        'booking_time',
        'status',
        'notes',
        'payment_method',
        'payment_type',
        'dp_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'payment_status',
        'payment_date',
        'cancellation_reason',
        'confirmed_at',
        'completed_at',
        'service_type',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'dp_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    /**
     * Always return booking_time as a plain "HH:mm" string.
     */
    public function getBookingTimeAttribute($value): ?string
    {
        if (!$value) return null;
        // Already "HH:mm" or "HH:mm:ss"
        return substr($value, 0, 5);
    }

    /**
     * Always return booking_date as "YYYY-MM-DD" string for Android.
     */
    public function getBookingDateAttribute($value): ?string
    {
        if (!$value) return null;
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return substr((string) $value, 0, 10);
    }

    /**
     * Get formatted booking date for display (e.g., "Monday, 06 April 2026").
     */
    public function getFormattedBookingDateAttribute(): ?string
    {
        $date = $this->attributes['booking_date'] ?? null;
        if (!$date) return null;

        if ($date instanceof \Carbon\Carbon) {
            return $date->isoFormat('dddd, DD MMMM YYYY');
        }

        try {
            return \Carbon\Carbon::parse($date)->isoFormat('dddd, DD MMMM YYYY');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the user that made the booking
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pet for this booking
     */
    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the doctor for this booking
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the service for this booking
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the medical record for this booking
     */
    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /**
     * Compatibility alias for admin/payment views that expect a collection-style relation.
     * A booking can still have one medical record in practice, but the plural relation
     * keeps older eager-loading code and Blade checks working safely.
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Scope for pending bookings
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for today's bookings
     */
    public function scopeToday($query)
    {
        return $query->whereDate('booking_date', today());
    }

    /**
     * Scope for upcoming bookings
     */
    public function scopeUpcoming($query)
    {
        return $query->whereDate('booking_date', '>=', today())
            ->whereIn('status', ['pending', 'confirmed']);
    }
}
