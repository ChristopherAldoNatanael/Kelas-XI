<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParamedicAuthorization extends Model
{
    public const MODE_NONE = 'none';
    public const MODE_AVAILABLE = 'available';
    public const MODE_REAL_TIME = 'real_time';

    protected $fillable = [
        'clinic_id',
        'paramedic_id',
        'action_code',
        'supervision_mode',
        'effective_from',
        'effective_to',
        'approved_by',
        'approved_at',
        'is_active',
        'active_key',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function paramedic(): BelongsTo
    {
        return $this->belongsTo(Paramedic::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
