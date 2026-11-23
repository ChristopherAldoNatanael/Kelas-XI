<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan pengembalian uang untuk satu booking.
 *
 * Baris ini adalah BUKTI, bukan instruksi transfer: uang sudah dikembalikan
 * (manual oleh admin atau otomatis lewat Midtrans) lalu dicatat di sini. Karena
 * itu `amount` bersifat immutable — koreksi dilakukan dengan menghapus lalu
 * mencatat ulang, bukan dengan mengedit diam-diam.
 */
class BookingRefund extends Model
{
    public const METHODS = [
        'transfer' => 'Transfer bank',
        'cash' => 'Tunai',
        'ewallet' => 'E-wallet',
        // Dana diminta kembali ke metode pembayaran asal lewat API Midtrans,
        // tanpa perlu tahu nomor rekening pasien.
        'midtrans' => 'Midtrans (otomatis)',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'booking_id',
        'clinic_id',
        'amount',
        'method',
        'reference',
        'provider_refund_id',
        'proof_path',
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

    /** URL bukti transfer (null bila refund tunai / tidak ada lampiran). */
    public function getProofUrlAttribute(): ?string
    {
        if (! $this->proof_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->proof_path);
    }
}
