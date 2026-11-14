<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_PET_SHOP = 'pet_shop';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CHARGEBACK = 'chargeback';

    /**
     * Peringkat status: status tidak boleh bergerak mundur hanya karena
     * notifikasi terlambat (mis. `pending` setelah `paid`).
     */
    public const STATUS_RANK = [
        self::STATUS_UNPAID => 0,
        self::STATUS_FAILED => 1,
        self::STATUS_EXPIRED => 1,
        self::STATUS_CANCELLED => 1,
        self::STATUS_PENDING => 2,
        self::STATUS_PAID => 3,
        self::STATUS_REFUND_PENDING => 4,
        self::STATUS_PARTIALLY_REFUNDED => 5,
        self::STATUS_REFUNDED => 6,
        self::STATUS_CHARGEBACK => 7,
    ];

    protected $fillable = [
        'payment_code',
        'provider',
        'payment_type',
        'clinic_id',
        'user_id',
        'payable_type',
        'payable_id',
        'provider_order_id',
        'provider_transaction_id',
        'partner_id',
        'merchant_id',
        'environment',
        'gross_amount',
        'currency',
        'payment_method',
        'status',
        'provider_status',
        'snap_token',
        'redirect_url',
        'expires_at',
        'paid_at',
        'refunded_amount',
        'split_settings',
        'metadata',
        'last_reconciled_at',
        'reconcile_note',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'refunded_amount' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_reconciled_at' => 'datetime',
        'split_settings' => 'array',
        'metadata' => 'array',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class)->orderBy('created_at');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }

    public function scopePendingCheck(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PENDING])
            ->whereNotNull('provider_order_id');
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_PARTIALLY_REFUNDED,
            self::STATUS_REFUND_PENDING,
        ], true);
    }

    /**
     * Apakah transisi ke status baru diizinkan (monoton, tidak mundur).
     */
    public function canMoveTo(string $newStatus): bool
    {
        $currentRank = self::STATUS_RANK[$this->status] ?? 0;
        $newRank = self::STATUS_RANK[$newStatus] ?? 0;

        return $newRank >= $currentRank;
    }

    public static function generatePaymentCode(): string
    {
        return 'PAY-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
