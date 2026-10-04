<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicJoinRequest extends Model
{
    protected $fillable = [
        'clinic_id',
        'name',
        'email',
        'phone',
        'password_hash',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}