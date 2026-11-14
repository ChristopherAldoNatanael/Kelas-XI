<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun pembayaran Midtrans milik satu klinik.
 *
 * Status akun adalah hasil verifikasi kredensial, bukan pilihan UI:
 * - not_configured       : klinik belum pernah menyimpan kredensial sama sekali.
 * - pending_verification : kredensial tersimpan tetapi belum lolos uji koneksi.
 * - active               : versi kredensial aktif terverifikasi untuk environment aplikasi.
 * - invalid              : versi kredensial terakhir gagal uji (TIDAK fallback ke pusat).
 * - disabled             : dinonaktifkan admin (TIDAK fallback ke pusat).
 * - suspended            : ditangguhkan sistem/verifikasi.
 */
class ClinicPaymentAccount extends Model
{
    public const PROVIDER_MIDTRANS = 'midtrans';

    public const STATUS_NOT_CONFIGURED = 'not_configured';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_NOT_CONFIGURED,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_ACTIVE,
        self::STATUS_INVALID,
        self::STATUS_DISABLED,
        self::STATUS_SUSPENDED,
    ];

    /** Status yang boleh diubah menjadi aktif lewat verifikasi. */
    public const ACTIVATABLE_STATUSES = [
        self::STATUS_NOT_CONFIGURED,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_ACTIVE,
        self::STATUS_INVALID,
        self::STATUS_DISABLED,
    ];

    protected $fillable = [
        'clinic_id',
        'provider',
        'status',
        'active_environment',
        'current_credential_version_id',
        'last_validated_at',
        'last_validation_message',
        'production_approved_at',
        'production_approved_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_validated_at' => 'datetime',
        'production_approved_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function credentialVersions(): HasMany
    {
        return $this->hasMany(PaymentCredentialVersion::class, 'payment_account_id')
            ->orderByDesc('version');
    }

    public function currentCredentialVersion(): BelongsTo
    {
        return $this->belongsTo(PaymentCredentialVersion::class, 'current_credential_version_id');
    }

    public function routingLogs(): HasMany
    {
        return $this->hasMany(PaymentRoutingLog::class, 'payment_account_id');
    }

    /**
     * Apakah klinik pernah menyimpan/mencoba konfigurasi.
     *
     * Ini pembeda penting antara "belum pernah mengatur" (fallback pusat boleh)
     * dan "sudah mengatur tetapi tidak valid" (fallback pusat DILARANG).
     */
    public function hasEverBeenConfigured(): bool
    {
        if ($this->status !== self::STATUS_NOT_CONFIGURED) {
            return true;
        }

        return $this->credentialVersions()->exists();
    }

    public function isActiveFor(string $environment): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->active_environment === $environment
            && $this->currentCredentialVersion !== null
            && $this->currentCredentialVersion->isUsableFor($environment);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_VERIFICATION => __('payments.status_pending_verification'),
            self::STATUS_ACTIVE => __('payments.status_active'),
            self::STATUS_INVALID => __('payments.status_invalid'),
            self::STATUS_DISABLED => __('payments.status_disabled'),
            self::STATUS_SUSPENDED => __('payments.status_suspended'),
            default => __('payments.status_not_configured'),
        };
    }

    /** Kelas badge Tailwind untuk status akun. */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            self::STATUS_PENDING_VERIFICATION => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            self::STATUS_INVALID => 'bg-rose-50 text-rose-700 ring-rose-600/20',
            self::STATUS_DISABLED, self::STATUS_SUSPENDED => 'bg-slate-100 text-slate-600 ring-slate-500/20',
            default => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }
}
