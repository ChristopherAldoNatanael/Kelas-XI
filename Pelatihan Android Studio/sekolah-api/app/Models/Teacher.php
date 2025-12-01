<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama',
        'nip',
        'teacher_code',
        'position',
        'department',
        'expertise',
        'certification',
        'join_date',
        'status'
    ];

    protected $casts = [
        'join_date' => 'date',
        'status' => 'string',
    ];

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isRetired(): bool
    {
        return $this->status === 'retired';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByExpertise($query, $expertise)
    {
        return $query->where('expertise', $expertise);
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'guru_id');
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    // Accessors for backward compatibility
    public function getNameAttribute(): ?string
    {
        return $this->nama;
    }

}
