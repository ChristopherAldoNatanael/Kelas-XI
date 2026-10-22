<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengecualian jadwal dokter untuk satu tanggal.
 *
 * - `is_closed = true`  → dokter cuti sehari penuh.
 * - `is_closed = false` → jam praktik tanggal itu diganti start/end di baris ini.
 *
 * Jadwal mingguan di `doctors` tetap menjadi default; baris ini hanya menimpa
 * satu tanggal dan tidak pernah mengubah data lama.
 */
class DoctorScheduleException extends Model
{
    protected $fillable = [
        'doctor_id',
        'clinic_id',
        'date',
        'is_closed',
        'start_time',
        'end_time',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_closed' => 'boolean',
    ];

    /**
     * MySQL menyimpan TIME sebagai "HH:MM:SS"; kontrak API memakai "HH:MM".
     */
    public function getStartTimeAttribute($value): ?string
    {
        return $value ? substr((string) $value, 0, 5) : null;
    }

    public function getEndTimeAttribute($value): ?string
    {
        return $value ? substr((string) $value, 0, 5) : null;
    }

    /**
     * Tanggal selalu dikirim sebagai "YYYY-MM-DD" agar Android tidak perlu
     * mem-parse datetime lengkap.
     */
    public function getDateAttribute($value): ?string
    {
        if (!$value) return null;
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return substr((string) $value, 0, 10);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('date', '>=', today());
    }
}
