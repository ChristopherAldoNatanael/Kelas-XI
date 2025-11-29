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
        'status',
    ];

    // Accessors & Mutators for Indonesian field names
    public function getNamaAttribute(): string
    {
        return (string) ($this->getAttributes()['nama'] ?? '');
    }

    public function setNamaAttribute($value)
    {
        $this->attributes['nama'] = $value;
    }

    public function getKodeAttribute(): string
    {
        return (string) ($this->getAttributes()['kode'] ?? '');
    }

    public function setKodeAttribute($value)
    {
        $this->attributes['kode'] = $value;
    }

    // Backward compatibility accessor for nama_mapel
    public function getNamaMapelAttribute(): string
    {
        return $this->nama;
    }

    public function setNamaMapelAttribute($value)
    {
        $this->attributes['nama'] = $value;
    }

    // Backward compatibility accessor for 'name' (English alias)
    public function getNameAttribute(): string
    {
        return $this->nama;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['nama'] = $value;
    }

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'subject_id');
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
