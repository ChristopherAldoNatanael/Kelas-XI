<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pickup pass (Click & Collect).
 *
 * `token` adalah nilai acak yang dikirim di QR; QR tidak pernah berisi order
 * id berurutan atau data pribadi. Pass hanya aktif setelah klinik menyiapkan
 * pesanan dan otomatis dicabut setelah dipakai / pesanan dibatalkan.
 */
class ProductPickupPass extends Model
{
    protected $fillable = [
        'order_id',
        'clinic_id',
        'user_id',
        'token',
        'manual_code',
        'expires_at',
        'is_active',
        'used_at',
        'used_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /** Jangan pernah mengekspos token manual ke serialisasi default. */
    protected $hidden = ['token', 'manual_code'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && $this->used_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
