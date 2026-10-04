<?php

namespace App\Models;

use App\Traits\HasPhotoUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasPhotoUrl;

    protected $appends = ['photo_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'password',
        'role',
        'phone',
        'photo',
        'clinic_id',
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
        ];
    }

    /**
     * Get the clinic this user belongs to
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Check if user is admin (legacy)
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'clinic_admin']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is clinic admin
     */
    public function isClinicAdmin(): bool
    {
        return $this->role === 'clinic_admin';
    }

    /**
     * Check if user is doctor
     */
    public function isDoctor(): bool
    {
        return $this->role === 'doctor';
    }

    /**
     * Get user's pets
     */
    public function pets()
    {
        return $this->hasMany(Pet::class);
    }

    /**
     * Get user's bookings
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get user's device tokens for FCM
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
}
