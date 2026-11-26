<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hasil laboratorium terstruktur (satu baris per parameter) supaya nilai dan
 * rentang rujukan bisa dibandingkan antar kunjungan, bukan hanya PDF lampiran.
 */
class LabResult extends Model
{
    protected $fillable = [
        'medical_record_id',
        'test_name',
        'parameter',
        'value',
        'unit',
        'reference_range',
        'flag',
        'performed_at',
        'notes',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }
}
