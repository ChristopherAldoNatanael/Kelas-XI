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
        'clinic_id',
        'last_reminder_at',
        // Antrian harian (lihat QueueService). Dipisah dari `status` karena
        // administrasi booking dan kehadiran fisik adalah dua hal berbeda.
        'queue_number',
        'queue_date',
        'queue_status',
        'checked_in_at',
        'called_at',
        'queue_finished_at',
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
        'last_reminder_at' => 'datetime',
        'queue_number' => 'integer',
        'queue_date' => 'date:Y-m-d',
        'checked_in_at' => 'datetime',
        'called_at' => 'datetime',
        'queue_finished_at' => 'datetime',
    ];

    /** Sudah punya nomor antrian hari ini? */
    public function hasQueueToday(): bool
    {
        return $this->queue_number !== null
            && $this->queue_date?->toDateString() === today()->toDateString();
    }

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
     * Get the clinic this booking belongs to
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
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
     * Catatan pengembalian uang (bisa lebih dari satu kali).
     */
    public function refunds()
    {
        return $this->hasMany(BookingRefund::class);
    }

    /**
     * Get the medical record for this booking
     */
    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
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
