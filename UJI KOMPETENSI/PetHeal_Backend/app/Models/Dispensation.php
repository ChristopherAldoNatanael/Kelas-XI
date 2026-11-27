<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispensation extends Model
{
    public const KIND_DISPENSE = 'dispense';
    public const KIND_REVERSAL = 'reversal';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_PARTIALLY_REVERSED = 'partially_reversed';

    protected $fillable = [
        'clinic_id',
        'prescription_id',
        'dispensation_number',
        'dispensed_by',
        'dispensed_at',
        'kind',
        'status',
        'idempotency_key',
        'reversal_of_id',
        'reason',
        'notes',
    ];

    protected $casts = [
        'dispensed_at' => 'datetime',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispensationItem::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}
