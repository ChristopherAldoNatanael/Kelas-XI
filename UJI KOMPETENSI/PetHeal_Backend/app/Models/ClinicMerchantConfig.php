<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konfigurasi merchant Midtrans milik satu klinik.
 *
 * `merchant_id` TIDAK BOLEH berasal dari request Android. Nilai di sini hanya
 * diubah oleh super_admin lewat VCMS setelah onboarding diverifikasi.
 */
class ClinicMerchantConfig extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';

    public const ONBOARDING_STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_ACTIVE,
        self::STATUS_REJECTED,
        self::STATUS_SUSPENDED,
    ];

    protected $fillable = [
        'clinic_id',
        'provider',
        'environment',
        'merchant_id',
        'partner_id',
        'onboarding_status',
        'is_online_payment_enabled',
        'commission_percent',
        'verified_at',
        'last_synced_at',
        'notes',
    ];

    protected $casts = [
        'is_online_payment_enabled' => 'boolean',
        'commission_percent' => 'decimal:2',
        'verified_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Syarat klinik boleh menerima pembayaran online.
     *
     * Semua syarat harus terpenuhi; tidak ada fallback diam-diam ke merchant
     * pusat. Bila gagal, UI hanya menawarkan "Bayar di Klinik".
     */
    public function isReadyForOnlinePayment(): bool
    {
        return $this->merchant_id !== null
            && trim((string) $this->merchant_id) !== ''
            && $this->is_online_payment_enabled
            && $this->onboarding_status === self::STATUS_ACTIVE
            && $this->verified_at !== null
            && $this->environment === config('services.midtrans.environment', 'sandbox');
    }

    /**
     * Alasan teknis (bukan pesan rahasia) kenapa online payment tidak aktif.
     */
    public function onlinePaymentBlockReason(): ?string
    {
        if ($this->isReadyForOnlinePayment()) {
            return null;
        }

        return match (true) {
            blank($this->merchant_id) => 'merchant_id_missing',
            !$this->is_online_payment_enabled => 'online_payment_disabled',
            $this->onboarding_status !== self::STATUS_ACTIVE => 'merchant_not_active',
            $this->verified_at === null => 'merchant_not_verified',
            default => 'environment_mismatch',
        };
    }
}
