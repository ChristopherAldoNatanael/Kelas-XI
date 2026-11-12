<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRefund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pencatatan pengembalian uang (manual oleh admin).
 *
 * Aturan yang dijaga:
 *  1. Tidak boleh mengembalikan lebih dari yang PERNAH DIBAYAR. Uang yang
 *     belum masuk tidak mungkin dikembalikan.
 *  2. Total seluruh refund tidak boleh melebihi `paid_amount`.
 *  3. Hanya booking yang dibatalkan yang boleh direfund — kalau masih
 *     berjalan, itu pembatalan, bukan refund diam-diam.
 *  4. Perubahan tercatat satu transaksi bersama, dengan lock baris, supaya
 *     dua admin tidak bisa mencatat refund melebihi batas secara bersamaan.
 */
class RefundService
{
    /**
     * @throws RuntimeException bila melebihi batas atau booking tidak layak.
     */
    public function record(
        Booking $booking,
        float $amount,
        string $method,
        ?string $reference,
        string $reason,
        ?int $userId = null
    ): BookingRefund {
        if ($amount <= 0) {
            throw new RuntimeException('Nominal refund harus lebih dari nol.');
        }

        if (!array_key_exists($method, BookingRefund::METHODS)) {
            throw new RuntimeException('Cara pengembalian tidak dikenal.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Alasan refund wajib diisi.');
        }

        return DB::transaction(function () use ($booking, $amount, $method, $reference, $reason, $userId) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'cancelled') {
                throw new RuntimeException(
                    'Refund hanya untuk booking yang sudah dibatalkan. Batalkan booking terlebih dahulu.'
                );
            }

            $paid = (float) $locked->paid_amount;
            $alreadyRefunded = (float) BookingRefund::where('booking_id', $locked->id)->sum('amount');
            $refundable = max(0, $paid - $alreadyRefunded);

            if ($refundable <= 0.009) {
                throw new RuntimeException(
                    'Tidak ada dana yang bisa dikembalikan: yang dibayar '
                    . $this->money($paid) . ', sudah dikembalikan ' . $this->money($alreadyRefunded) . '.'
                );
            }

            if ($amount - 0.009 > $refundable) {
                throw new RuntimeException(
                    'Nominal melebihi dana yang bisa dikembalikan (' . $this->money($refundable) . ').'
                );
            }

            return BookingRefund::create([
                'booking_id' => $locked->id,
                'clinic_id' => $locked->clinic_id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'reason' => trim($reason),
                'recorded_by' => $userId ?? auth()->id(),
            ]);
        });
    }

    /** Total yang sudah dikembalikan untuk satu booking. */
    public function refundedTotal(Booking $booking): float
    {
        return (float) BookingRefund::where('booking_id', $booking->id)->sum('amount');
    }

    /** Dana yang masih bisa dikembalikan. */
    public function refundableAmount(Booking $booking): float
    {
        return max(0, (float) $booking->paid_amount - $this->refundedTotal($booking));
    }

    private function money(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}
