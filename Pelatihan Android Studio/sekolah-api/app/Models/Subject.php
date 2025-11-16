<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'kode',
        'category',
        'description',
        'credit_hours',
        'semester',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'mata_pelajaran', 'nama');
    }

    // Accessors for API compatibility
    public function getNameAttribute()
    {
        return $this->nama;
    }

    public function getCodeAttribute()
    {
        return $this->kode;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
