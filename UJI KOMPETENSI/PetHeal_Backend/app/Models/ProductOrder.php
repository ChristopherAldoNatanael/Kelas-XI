<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pesanan Pet Shop.
 *
 * Status pesanan dan status pembayaran SENGAJA dipisah: satu pesanan bisa
 * sudah dibayar tetapi belum diterima klinik, atau sudah selesai dibayar tunai
 * di klinik. Jangan pernah menyimpulkan salah satu dari yang lain.
 */
class ProductOrder extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_EXPIRED = 'expired';
    public const PAYMENT_CANCELLED = 'cancelled';
    public const PAYMENT_REFUND_PENDING = 'refund_pending';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_CHARGEBACK = 'chargeback';

    public const METHOD_ONLINE = 'online';
    public const METHOD_PAY_AT_CLINIC = 'pay_at_clinic';

    /** Status akhir: tidak ada transisi keluar lagi. */
    public const FINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    /**
     * Transisi status pesanan yang sah. Semua perubahan WAJIB lewat peta ini
     * supaya status tidak bisa melompat atau mundur.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING_PAYMENT => [
            self::STATUS_AWAITING_CONFIRMATION,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ],
        self::STATUS_AWAITING_CONFIRMATION => [
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_ACCEPTED => [
            self::STATUS_PREPARING,
            self::STATUS_REJECTED,
        ],
        self::STATUS_PREPARING => [
            self::STATUS_READY_FOR_PICKUP,
            self::STATUS_REJECTED,
        ],
        self::STATUS_READY_FOR_PICKUP => [
            self::STATUS_COMPLETED,
        ],
    ];

    protected $fillable = [
        'order_number',
        'user_id',
        'clinic_id',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_total',
        'total_amount',
        'currency',
        'pickup_clinic_name',
        'pickup_clinic_address',
        'pickup_clinic_phone',
        'customer_name',
        'customer_phone',
        'notes',
        'stock_reserved_at',
        'reservation_expires_at',
        'stock_committed_at',
        'stock_released_at',
        'confirmed_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'discount_total' => 'integer',
        'total_amount' => 'integer',
        'stock_reserved_at' => 'datetime',
        'reservation_expires_at' => 'datetime',
        'stock_committed_at' => 'datetime',
        'stock_released_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProductOrderStatusHistory::class)->orderBy('created_at');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest('id')->first();
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::FINAL_STATUSES);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function canTransitionTo(string $next): bool
    {
        return in_array($next, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isPayAtClinic(): bool
    {
        return $this->payment_method === self::METHOD_PAY_AT_CLINIC;
    }

    /**
     * Reservasi masih menahan stok (belum dilepas / belum dikomit).
     */
    public function holdsReservation(): bool
    {
        return $this->stock_committed_at === null && $this->stock_released_at === null;
    }

    public static function generateOrderNumber(): string
    {
        return 'SHOP-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
