<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'medical_record_id',
        'clinic_id',
        'referred_to',
        'reason',
        'notes',
        'status',
        'referred_at',
        'returned_at',
        'result_notes',
    ];

    protected $casts = [
        'referred_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }
}
