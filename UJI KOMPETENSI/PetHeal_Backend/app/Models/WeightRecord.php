<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightRecord extends Model
{
    protected $fillable = [
        'pet_id',
        'weight',
        'recorded_at',
        'notes',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'recorded_at' => 'date:Y-m-d',
    ];

    /**
     * Get the pet that owns this weight record.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Scope for records in date order.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('recorded_at', 'asc');
    }

    /**
     * Scope for recent records first.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }
}
