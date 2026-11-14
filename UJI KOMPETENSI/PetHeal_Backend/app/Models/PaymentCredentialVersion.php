<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versi kredensial Midtrans milik satu klinik.
 *
 * Satu versi tidak pernah ditimpa: perubahan kredensial membuat versi baru,
 * sehingga transaksi lama tetap dapat diverifikasi dengan Server Key yang
 * dipakai saat transaksi dibuat.
 *
 * `server_key` memakai encrypted cast — nilai asli tidak pernah muncul di
 * serialisasi JSON, log, atau halaman VCMS.
 */
class PaymentCredentialVersion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VALID,
        self::STATUS_INVALID,
        self::STATUS_RETIRED,
    ];

    protected $fillable = [
        'payment_account_id',
        'environment',
        'merchant_id',
        'client_key',
        'server_key',
        'version',
        'status',
        'is_active',
        'validation_message',
        'last_validated_at',
        'activated_at',
        'retired_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'server_key' => 'encrypted',
        'is_active' => 'boolean',
        'version' => 'integer',
        'last_validated_at' => 'datetime',
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    protected $hidden = [
        'server_key',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ClinicPaymentAccount::class, 'payment_account_id');
    }

    public function hasCredentials(): bool
    {
        return filled($this->merchant_id)
            && filled($this->client_key)
            && filled($this->server_key);
    }

    /**
     * Versi boleh dipakai membuat transaksi baru pada environment tertentu.
     * Versi `retired` tetap dapat dipakai untuk route yang sudah terkunci.
     */
    public function isUsableFor(string $environment): bool
    {
        return $this->environment === $environment
            && $this->hasCredentials()
            && in_array($this->status, [self::STATUS_VALID], true)
            && $this->is_active;
    }

    public function maskedMerchantId(): string
    {
        $value = (string) $this->merchant_id;

        if ($value === '') {
            return '-';
        }

        return strlen($value) <= 6
            ? $value
            : substr($value, 0, 3) . str_repeat('•', max(2, strlen($value) - 6)) . substr($value, -3);
    }
}
