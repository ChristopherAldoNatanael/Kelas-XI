<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tanda vital satu kunjungan. Dipisah dari SOAP agar bisa dihitung, dibuat
 * grafiknya, dan dibandingkan antar kunjungan tanpa membaca teks bebas.
 */
class MedicalRecordVitals extends Model
{
    protected $fillable = [
        'medical_record_id',
        'temperature_c',
        'heart_rate',
        'respiratory_rate',
        'crt_seconds',
        'mucosa',
        'hydration',
        'bcs',
        'pain_score',
        'weight_kg',
        'notes',
        'measured_at',
    ];

    protected $casts = [
        'temperature_c' => 'decimal:1',
        'crt_seconds' => 'decimal:1',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'bcs' => 'integer',
        'pain_score' => 'integer',
        'weight_kg' => 'decimal:2',
        'measured_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }
}
