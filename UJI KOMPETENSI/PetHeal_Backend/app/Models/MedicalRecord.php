<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $fillable = [
        'booking_id',
        'pet_id',
        'doctor_id',
        'diagnosis',
        'treatment',
        'medicine',
        'notes',
        'next_visit_date',
        'next_visit_time',
        'reminder_sent',
        'cost',
        'treatment_cost',
        'medicine_cost',
    ];

    protected $casts = [
        'next_visit_date' => 'date:Y-m-d',
        'next_visit_time' => 'string',
        'reminder_sent'   => 'boolean',
    ];

    /**
     * Get the booking associated with this record
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the pet associated with this record
     */
    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the doctor who created this record
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Scope for records with upcoming visits
     */
    public function scopeUpcomingVisits($query)
    {
        return $query->whereDate('next_visit_date', '>=', today())
            ->where('reminder_sent', false);
    }

    /**
     * Scope for records with visits tomorrow
     */
    public function scopeVisitsTomorrow($query)
    {
        return $query->whereDate('next_visit_date', today()->addDay())
            ->where('reminder_sent', false);
    }
}
