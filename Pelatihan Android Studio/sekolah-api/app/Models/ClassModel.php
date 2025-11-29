<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'nama_kelas',
        'kode_kelas',
        'level',
        'major',
        'academic_year',
        'homeroom_teacher_id',
        'capacity',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'kelas', 'nama_kelas');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'class_id');
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    // Scopes
    public function scopeByName($query, $name)
    {
        return $query->where('nama_kelas', 'like', '%' . $name . '%');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }


    // Accessors & Mutators for Indonesian field names
    public function getNamaKelasAttribute(): ?string
    {
        return $this->getAttributes()['nama_kelas'] ?? '';
    }

    public function setNamaKelasAttribute($value)
    {
        $this->attributes['nama_kelas'] = $value;
    }

    public function getKodeKelasAttribute(): ?string
    {
        return $this->getAttributes()['kode_kelas'] ?? '';
    }

    public function setKodeKelasAttribute($value)
    {
        $this->attributes['kode_kelas'] = $value;
    }

    public function getTingkatKelasAttribute(): ?string
    {
        return $this->getAttributes()['level'] ?? '';
    }

    public function setTingkatKelasAttribute($value)
    {
        $this->attributes['level'] = $value;
    }

    public function getTahunAjaranAttribute(): ?string
    {
        return $this->getAttributes()['academic_year'] ?? '';
    }

    public function setTahunAjaranAttribute($value)
    {
        $this->attributes['academic_year'] = $value;
    }

    public function getFullNameAttribute(): string
    {
        return 'Level ' . $this->level . ' - ' . $this->nama_kelas;
    }
}
