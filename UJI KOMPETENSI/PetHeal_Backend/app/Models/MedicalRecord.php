<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $fillable = [
        'booking_id',
        'pet_id',
        'doctor_id',
        // PHASE 1 (C4): column exists (2026_10_02_000006, backfilled in
        // 000007). Previously missing here, so Admin/MedicalRecordController
        // silently dropped `clinic_id` on create and clinic-scoped queries
        // could never match those rows.
        'clinic_id',
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
        'total_medical_cost',
        'extra_payment_amount',
        'extra_payment_paid_amount',
        'extra_payment_status',
        'extra_payment_order_id',
        'extra_payment_date',
    ];

    protected $casts = [
        'next_visit_date' => 'date:Y-m-d',
        'next_visit_time' => 'string',
        'reminder_sent'   => 'boolean',
        'cost' => 'decimal:2',
        'treatment_cost' => 'decimal:2',
        'medicine_cost' => 'decimal:2',
        'total_medical_cost' => 'decimal:2',
        'extra_payment_amount' => 'decimal:2',
        'extra_payment_paid_amount' => 'decimal:2',
        'extra_payment_date' => 'datetime',
    ];

    public function recalculatePaymentState(float $bookingPaidAmount = 0): void
    {
        $consultationCost = (float) ($this->cost ?? 0);
        $treatmentCost = (float) ($this->treatment_cost ?? 0);
        $medicineCost = (float) ($this->medicine_cost ?? 0);
        $totalMedicalCost = $consultationCost + $treatmentCost + $medicineCost;
        $extraAmount = max(0, $totalMedicalCost - $bookingPaidAmount);
        $paidExtra = min((float) ($this->extra_payment_paid_amount ?? 0), $extraAmount);

        $this->total_medical_cost = $totalMedicalCost;
        $this->extra_payment_amount = $extraAmount;
        $this->extra_payment_paid_amount = $paidExtra;

        if ($extraAmount <= 0) {
            $this->extra_payment_status = 'not_required';
            $this->extra_payment_order_id = null;
            $this->extra_payment_date = null;
        } elseif ($paidExtra >= $extraAmount) {
            $this->extra_payment_status = 'paid';
        } elseif ($this->extra_payment_status !== 'pending') {
            $this->extra_payment_status = 'unpaid';
        }
    }

    public function getCanViewFullRecordAttribute(): bool
    {
        return in_array($this->extra_payment_status, ['not_required', 'paid'], true);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

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
