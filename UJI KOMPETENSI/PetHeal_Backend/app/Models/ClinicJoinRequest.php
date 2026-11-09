<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicJoinRequest extends Model
{
    /** Bergabung ke klinik yang sudah ada (alur lama). */
    public const TYPE_JOIN = 'join';

    /** Pemilik mendaftarkan klinik baru — klinik dibuat saat disetujui. */
    public const TYPE_NEW_CLINIC = 'new_clinic';

    protected $fillable = [
        'type',
        'clinic_id',
        'name',
        'email',
        'phone',
        'password_hash',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'requested_clinic_name',
        'requested_clinic_slug',
        'requested_clinic_address',
        'requested_clinic_phone',
        'requested_clinic_email',
        'requested_primary_color',
        'requested_description',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNewClinic($query)
    {
        return $query->where('type', self::TYPE_NEW_CLINIC);
    }

    public function isNewClinic(): bool
    {
        return $this->type === self::TYPE_NEW_CLINIC;
    }

    /** Nama klinik yang ditampilkan: klinik existing atau snapshot klinik baru. */
    public function displayClinicName(): string
    {
        return $this->clinic?->name
            ?? $this->requested_clinic_name
            ?? '—';
    }

    /** Warna klinik untuk badge/avatar di daftar permintaan. */
    public function displayClinicColor(): string
    {
        return $this->clinic?->primary_color
            ?? $this->requested_primary_color
            ?? '#18C964';
    }
}
