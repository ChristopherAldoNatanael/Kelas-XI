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
        // Using 'mata_pelajaran' field to match with 'nama' field in subjects table
        return $this->belongsTo(Subject::class, 'mata_pelajaran', 'nama');
    }

    // Safeguard method to safely get subject data
    public function getSubjectSafely()
    {
        try {
            return $this->subject;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Subject relationship error for schedule', [
                'schedule_id' => $this->id,
                'mata_pelajaran' => $this->mata_pelajaran,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'kelas', 'nama_kelas');
    }

    // Safeguard method to safely get class data
    public function getClassSafely()
    {
        try {
            return $this->class;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Class relationship error for schedule', [
                'schedule_id' => $this->id,
                'kelas' => $this->kelas,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'schedule_id');
    }

    // Safeguard method to safely get teacher data
    public function getTeacherSafely()
    {
        try {
            return $this->teacher;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Teacher relationship error for schedule', [
                'schedule_id' => $this->id,
                'guru_id' => $this->guru_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
        $className = $this->class ? ($this->class->name ?? 'Unknown Class') : 'Unknown Class';
        $subjectName = $this->subject ? ($this->subject->name ?? 'Unknown Subject') : 'Unknown Subject';
        $teacherName = $this->teacher && $this->teacher->user ? ($this->teacher->user->nama ?? 'Unknown Teacher') : 'Unknown Teacher';

        return $className . ' | ' . $subjectName . ' | ' . $teacherName;
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
        $classData = null;
        if ($this->class) {
            $classData = [
                'id' => $this->class->id,
                'name' => $this->class->name ?? 'Unknown',
                'level' => $this->class->level ?? null,
                'major' => $this->class->major ?? null
            ];
        }

        $subjectData = null;
        if ($this->subject) {
            $subjectData = [
                'id' => $this->subject->id,
                'name' => $this->subject->name ?? 'Unknown',
                'category' => $this->subject->category ?? null
            ];
        }

        $teacherData = null;
        if ($this->teacher && $this->teacher->user) {
            $teacherData = [
                'id' => $this->teacher->id,
                'name' => $this->teacher->user->nama ?? 'Unknown',
                'position' => $this->teacher->position ?? null
            ];
        }

        $classroomData = null;
        if ($this->classroom) {
            $classroomData = [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name ?? 'Unknown',
                'type' => $this->classroom->type ?? null,
                'capacity' => $this->classroom->capacity ?? null
            ];
        }

        return [
            'id' => $this->id,
            'class' => $classData,
            'subject' => $subjectData,
            'teacher' => $teacherData,
            'classroom' => $classroomData,
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->day_name,
            'period_number' => $this->period_number,
            'start_time' => $this->start_time ? $this->start_time->format('H:i:s') : null,
            'end_time' => $this->end_time ? $this->end_time->format('H:i:s') : null,
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
            'start_time' => $this->start_time ? $this->start_time->format('H:i') : null,
            'end_time' => $this->end_time ? $this->end_time->format('H:i') : null,
            'subject_name' => $this->subject ? ($this->subject->name ?? 'Unknown') : 'Unknown',
            'teacher_name' => $this->teacher && $this->teacher->user ? ($this->teacher->user->nama ?? 'Unknown') : 'Unknown',
            'classroom_name' => $this->classroom ? ($this->classroom->name ?? 'No Room') : 'No Room',
            'notes' => $this->notes
        ];
    }
}
