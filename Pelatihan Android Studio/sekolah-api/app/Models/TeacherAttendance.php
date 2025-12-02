<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Schedule;

class TeacherAttendance extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_HADIR = 'hadir';
    public const STATUS_TELAT = 'telat';
    public const STATUS_TIDAK_HADIR = 'tidak_hadir';
    public const STATUS_DIGANTI = 'diganti';
    public const STATUS_IZIN = 'izin';

    // Default attributes - status default adalah 'pending'
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'schedule_id',
        'guru_id',
        'guru_asli_id',
        'tanggal',
        'jam_masuk',
        'status',
        'keterangan',
        'created_by',
        'assigned_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam_masuk' => 'datetime:H:i:s',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'guru_id');
    }

    public function guruAsli(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'guru_asli_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Safeguard methods to safely get related data
    public function getScheduleSafely()
    {
        try {
            return $this->schedule;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('TeacherAttendance schedule relationship error', [
                'attendance_id' => $this->id,
                'schedule_id' => $this->schedule_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getGuruSafely()
    {
        try {
            return $this->guru;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('TeacherAttendance guru relationship error', [
                'attendance_id' => $this->id,
                'guru_id' => $this->guru_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // Scopes
    public function scopeByDate($query, $date)
    {
        return $query->where('tanggal', $date);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByGuru($query, $guruId)
    {
        return $query->where('guru_id', $guruId);
    }

    public function scopeHadir($query)
    {
        return $query->where('status', 'hadir');
    }

    public function scopeTelat($query)
    {
        return $query->where('status', 'telat');
    }

    public function scopeTidakHadir($query)
    {
        return $query->where('status', 'tidak_hadir');
    }

    public function scopeDiganti($query)
    {
        return $query->where('status', 'diganti');
    }

    // Helper methods
    public function isHadir(): bool
    {
        return $this->status === 'hadir';
    }

    public function isTelat(): bool
    {
        return $this->status === 'telat';
    }

    public function isTidakHadir(): bool
    {
        return $this->status === 'tidak_hadir';
    }

    public function isDiganti(): bool
    {
        return $this->status === 'diganti';
    }
}
