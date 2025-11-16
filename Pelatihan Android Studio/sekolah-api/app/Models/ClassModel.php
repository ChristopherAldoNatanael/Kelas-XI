<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    // Accessors for API compatibility
    public function getNameAttribute()
    {
        return $this->nama_kelas;
    }

    public function getCodeAttribute()
    {
        return $this->kode_kelas;
    }

    // Scopes
    public function scopeByKode($query, $kode)
    {
        return $query->where('kode_kelas', $kode);
    }

    public function scopeByNama($query, $nama)
    {
        return $query->where('nama_kelas', 'like', '%' . $nama . '%');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return $this->kode_kelas . ' - ' . $this->nama_kelas;
    }
}
