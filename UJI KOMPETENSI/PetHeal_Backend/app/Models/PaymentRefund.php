<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'payment_id',
        'refund_code',
        'amount',
        'reason',
        'status',
        'provider_refund_id',
        'requested_by',
        'processed_at',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public static function generateRefundCode(): string
    {
        return 'RFD-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
