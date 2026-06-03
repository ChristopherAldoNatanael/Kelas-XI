<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    protected $fillable = [
        'pet_id',
        'vaccine_name',
        'batch_number',
        'date_administered',
        'next_due_date',
        'veterinarian',
        'notes',
        'reminder_sent',
    ];

    protected $casts = [
        'date_administered' => 'date:Y-m-d',
        'next_due_date' => 'date:Y-m-d',
        'reminder_sent' => 'boolean',
    ];

    /**
     * Get the pet that owns this vaccination.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Scope for upcoming due vaccinations.
     */
    public function scopeUpcomingDue($query, int $daysAhead = 30)
    {
        return $query->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays($daysAhead))
            ->where('reminder_sent', false);
    }

    /**
     * Scope for overdue vaccinations.
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('next_due_date')
            ->where('next_due_date', '<', now())
            ->where('reminder_sent', false);
    }

    /**
     * Check if vaccination is due soon.
     */
    public function isDueSoon(int $daysAhead = 30): bool
    {
        if (!$this->next_due_date) {
            return false;
        }
        
        return $this->next_due_date->lte(now()->addDays($daysAhead));
    }

    /**
     * Check if vaccination is overdue.
     */
    public function isOverdue(): bool
    {
        if (!$this->next_due_date) {
            return false;
        }
        
        return $this->next_due_date->isPast();
    }
}
