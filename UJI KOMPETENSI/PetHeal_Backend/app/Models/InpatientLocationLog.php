<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InpatientLocationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'clinic_id', 'inpatient_stay_id', 'from_ward', 'from_kennel_code',
        'to_ward', 'to_kennel_code', 'moved_at', 'moved_by', 'reason',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function stay()
    {
        return $this->belongsTo(InpatientStay::class, 'inpatient_stay_id');
    }
}
