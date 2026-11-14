<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak keputusan routing pembayaran.
 *
 * Ditulis setiap kali transaksi dibuat ATAU ditolak. Tidak pernah menyimpan
 * Server Key/Client Key — hanya identitas route dan alasannya, supaya klinik
 * bisa melihat kenapa pembayaran online tidak tersedia.
 */
class PaymentRoutingLog extends Model
{
    public const OUTCOME_RESOLVED = 'resolved';
    public const OUTCOME_REJECTED = 'rejected';

    protected $fillable = [
        'clinic_id',
        'payment_id',
        'payable_type',
        'payable_id',
        'environment',
        'routing_source',
        'payment_account_id',
        'credential_version_id',
        'merchant_id',
        'outcome',
        'reason',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
