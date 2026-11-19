<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan layanan yang belum tersedia di katalog klinik.
 *
 * Service request BUKAN appointment dan BUKAN pembayaran. Ia hanya berisi
 * kebutuhan pemilik hewan, hasil evaluasi klinik, dan penawaran. Booking baru
 * lahir ketika pemilik menyetujui penawaran.
 */
class ServiceRequest extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_WAITING_DOCTOR = 'waiting_doctor_review';
    public const STATUS_WAITING_OFFER = 'waiting_offer';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONVERTED = 'converted_to_booking';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_WAITING_DOCTOR,
        self::STATUS_WAITING_OFFER,
        self::STATUS_OFFERED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_CONVERTED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    /**
     * Transisi yang diizinkan. Dipakai backend untuk menolak lompatan status
     * (mis. submitted → converted tanpa penawaran).
     */
    public const TRANSITIONS = [
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED, self::STATUS_REJECTED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_WAITING_DOCTOR, self::STATUS_OFFERED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_WAITING_DOCTOR => [self::STATUS_WAITING_OFFER, self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_WAITING_OFFER => [self::STATUS_OFFERED, self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_OFFERED => [self::STATUS_ACCEPTED, self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED, self::STATUS_EXPIRED, self::STATUS_WAITING_OFFER, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED => [self::STATUS_CONVERTED, self::STATUS_CANCELLED],
        self::STATUS_EXPIRED => [self::STATUS_WAITING_OFFER, self::STATUS_CANCELLED],
        self::STATUS_REJECTED => [],
        self::STATUS_CONVERTED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const NEED_TYPES = [
        'consultation' => 'Konsultasi umum',
        'follow_up' => 'Kontrol lanjutan',
        'vaccination' => 'Vaksinasi',
        'grooming' => 'Grooming / perawatan',
        'surgery' => 'Tindakan / operasi',
        'laboratory' => 'Pemeriksaan laboratorium',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'code',
        'user_id',
        'pet_id',
        'clinic_id',
        'doctor_id',
        'medical_record_id',
        'booking_id',
        'need_type',
        'complaint',
        'preferred_date',
        'preferred_time',
        'status',
        'admin_notes',
        'doctor_recommendation',
        'doctor_notes',
        'needs_follow_up',
        'offer_service_id',
        'offer_service_name',
        'offer_description',
        'offer_price',
        'offer_price_type',
        'offer_duration_minutes',
        'offer_doctor_id',
        'offer_schedule_date',
        'offer_schedule_time',
        'offer_notes',
        'offer_valid_until',
        'offered_at',
        'accepted_at',
        'rejected_at',
        'owner_response_note',
    ];

    protected $casts = [
        'preferred_date' => 'date:Y-m-d',
        'offer_schedule_date' => 'date:Y-m-d',
        'offer_price' => 'decimal:2',
        'offer_duration_minutes' => 'integer',
        'needs_follow_up' => 'boolean',
        'offer_valid_until' => 'datetime',
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function offerDoctor()
    {
        return $this->belongsTo(Doctor::class, 'offer_doctor_id');
    }

    public function offerService()
    {
        return $this->belongsTo(Service::class, 'offer_service_id');
    }

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getNeedTypeLabelAttribute(): string
    {
        return self::NEED_TYPES[$this->need_type] ?? 'Lainnya';
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'Terkirim',
            self::STATUS_UNDER_REVIEW => 'Sedang ditinjau',
            self::STATUS_WAITING_DOCTOR => 'Menunggu evaluasi dokter',
            self::STATUS_WAITING_OFFER => 'Menunggu penawaran',
            self::STATUS_OFFERED => 'Penawaran tersedia',
            self::STATUS_ACCEPTED => 'Disetujui pemilik',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CONVERTED => 'Menjadi booking',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_EXPIRED => 'Penawaran kedaluwarsa',
            default => 'Tidak diketahui',
        };
    }

    public static function statusTone(?string $status): string
    {
        return match ($status) {
            self::STATUS_OFFERED, self::STATUS_ACCEPTED => 'primary',
            self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW, self::STATUS_WAITING_DOCTOR, self::STATUS_WAITING_OFFER => 'amber',
            self::STATUS_CONVERTED => 'blue',
            self::STATUS_REJECTED, self::STATUS_EXPIRED => 'rose',
            default => 'slate',
        };
    }

    /** True bila penawaran ada dan masa berlakunya sudah lewat. */
    public function isOfferExpired(): bool
    {
        return $this->status === self::STATUS_OFFERED
            && $this->offer_valid_until !== null
            && $this->offer_valid_until->isPast();
    }

    /** Boleh dinilai/diubah karena masih menunggu aksi salah satu pihak. */
    public function isOpen(): bool
    {
        return !in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_CONVERTED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
