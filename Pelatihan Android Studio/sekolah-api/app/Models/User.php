<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'mata_pelajaran',
        'is_banned'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    // Role-based Relationships
        // Teachers are now in separate teachers table
        // Students are users with role 'siswa'

    /**
     * Relasi ke kelas (untuk siswa)
     * Siswa memiliki satu kelas yang sedang ditempuh
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    // Activity Logs
    public function activityLogs() {}

    // Scopes
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getNamaAttribute()
    {
        return $this->name;
    }

    public function getRoleNameAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'kurikulum' => 'Kurikulum',
            'kepala_sekolah' => 'Kepala Sekolah',
            'siswa' => 'Siswa',
            default => ucfirst($this->role)
        };
    }

    public function getProfileAttribute()
    {
        return match ($this->role) {
            'siswa' => $this->student,
            default => null
        };
    }

    // Helper methods for role checking
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isKurikulum(): bool
    {
        return $this->role === 'kurikulum';
    }

    public function isKepalaSekolah(): bool
    {
        return $this->role === 'kepala_sekolah';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function isBanned(): bool
    {
        return $this->is_banned;
    }
}
