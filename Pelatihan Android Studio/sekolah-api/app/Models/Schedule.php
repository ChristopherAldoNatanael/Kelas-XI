<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'hari',
        'kelas',
        'mata_pelajaran',
        'guru_id',
        'jam_mulai',
        'jam_selesai',
        'ruang',
        'class_id',
        'subject_id',
        'teacher_id',
        'classroom_id',
        'day_of_week',
        'period_number',
        'start_time',
        'end_time',
        'academic_year',
        'semester',
        'status',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'jam_mulai' => 'datetime:H:i:s',
        'jam_selesai' => 'datetime:H:i:s',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'guru_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'guru_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran', 'nama');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'kelas', 'nama_kelas');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'ruang', 'nama');
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'schedule_id');
    }

    // Scopes
    public function scopeByDay($query, $day)
    {
        return $query->where('hari', $day);
    }

    public function scopeByClass($query, $className)
    {
        return $query->where('kelas', $className);
    }

    public function scopeByTeacher($query, $teacherId)
    {
        return $query->where('guru_id', $teacherId);
    }

    public function scopeBySubject($query, $subjectName)
    {
        return $query->where('mata_pelajaran', $subjectName);
    }

    // Accessors & Mutators
    public function getDurationAttribute(): string
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }

    public function getFullInfoAttribute(): string
    {
        return $this->class->name . ' | ' . $this->subject->name . ' | ' . $this->teacher->user->nama;
    }

    public function getDayNameAttribute(): string
    {
        return match ($this->day_of_week) {
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
            default => ucfirst($this->day_of_week)
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => '<span class="badge bg-success">Active</span>',
            'inactive' => '<span class="badge bg-secondary">Inactive</span>',
            'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
            default => '<span class="badge bg-warning">Unknown</span>'
        };
    }

    // OPTIMIZED: Scopes dengan minimal eager loading untuk performance
    public function scopeWithAllRelations($query)
    {
        // Hanya load field yang dibutuhkan untuk hemat memory
        return $query->with([
            'class:id,nama_kelas,kode_kelas',
            'subject:id,name,code',
            'teacher:id,user_id',
            'teacher.user:id,nama',
            'classroom:id,name,code'
        ])->select([
            'id',
            'class_id',
            'subject_id',
            'teacher_id',
            'classroom_id',
            'day_of_week',
            'period_number',
            'start_time',
            'end_time',
            'status'
        ]);
    }

    public function scopeActiveWithRelations($query)
    {
        return $query->where('status', 'active')
            ->with([
                'class:id,nama_kelas,kode_kelas',
                'subject:id,name',
                'teacher:id,user_id',
                'teacher.user:id,nama',
                'classroom:id,name'
            ]);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('subject', function ($sq) use ($search) {
                $sq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
                ->orWhereHas('teacher.user', function ($tq) use ($search) {
                    $tq->where('nama', 'like', "%{$search}%");
                })
                ->orWhereHas('class', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                });
        });
    }

    // Helper methods for API responses
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'class' => [
                'id' => $this->class->id,
                'name' => $this->class->name,
                'level' => $this->class->level,
                'major' => $this->class->major
            ],
            'subject' => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'category' => $this->subject->category ?? null
            ],
            'teacher' => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->user->nama,
                'position' => $this->teacher->position ?? null
            ],
            'classroom' => $this->classroom ? [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
                'type' => $this->classroom->type ?? null,
                'capacity' => $this->classroom->capacity ?? null
            ] : null,
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->day_name,
            'period_number' => $this->period_number,
            'start_time' => $this->start_time->format('H:i:s'),
            'end_time' => $this->end_time->format('H:i:s'),
            'duration' => $this->duration,
            'academic_year' => $this->academic_year,
            'semester' => $this->semester,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString()
        ];
    }

    public function toMobileArray(): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'period_number' => $this->period_number,
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'subject_name' => $this->subject->name,
            'teacher_name' => $this->teacher->user->nama,
            'classroom_name' => $this->classroom->name ?? 'No Room',
            'notes' => $this->notes
        ];
    }
}
