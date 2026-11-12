<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan pengembalian uang untuk satu booking.
 *
 * Baris ini adalah BUKTI, bukan instruksi transfer: uang sudah dikembalikan
 * manual oleh admin lalu dicatat di sini. Karena itu `amount` bersifat
 * immutable — koreksi dilakukan dengan menghapus lalu mencatat ulang, bukan
 * dengan mengedit diam-diam.
 */
class BookingRefund extends Model
{
    public const METHODS = [
        'transfer' => 'Transfer bank',
        'cash' => 'Tunai',
        'ewallet' => 'E-wallet',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'booking_id',
        'clinic_id',
        'amount',
        'method',
        'reference',
        'reason',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
