<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentNotification extends Model
{
    protected $fillable = [
        'payment_id',
        'provider',
        'provider_order_id',
        'signature_valid',
        'transaction_status',
        'status_code',
        'gross_amount',
        'payload',
        'processed',
        'processing_note',
        'ip_address',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed' => 'boolean',
        'payload' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
