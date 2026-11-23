<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Support\Money;
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
 *  5. `bookings.payment_status` selalu diselaraskan dengan uang yang sudah
 *     kembali (`refund_pending` / `refunded`), tidak pernah dibiarkan `paid`.
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
        ?int $userId = null,
        ?string $providerRefundId = null,
        ?string $proofPath = null
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

        return DB::transaction(function () use ($booking, $amount, $method, $reference, $reason, $userId, $providerRefundId, $proofPath) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'cancelled') {
                throw new RuntimeException(
                    'Refund hanya untuk booking yang sudah dibatalkan. Batalkan booking terlebih dahulu.'
                );
            }

            // Seluruh perbandingan uang memakai rupiah integer (Money::rupiah)
            // sehingga tidak ada lagi toleransi epsilon 0,009 yang membuat
            // batas refund bergantung pada pembulatan float.
            $paid = Money::rupiah($locked->paid_amount);
            $alreadyRefunded = Money::rupiah(BookingRefund::where('booking_id', $locked->id)->sum('amount'));
            $refundable = max(0, $paid - $alreadyRefunded);
            $amount = Money::rupiah($amount);

            if ($refundable <= 0) {
                throw new RuntimeException(
                    'Tidak ada dana yang bisa dikembalikan: yang dibayar '
                    . $this->money($paid) . ', sudah dikembalikan ' . $this->money($alreadyRefunded) . '.'
                );
            }

            if ($amount > $refundable) {
                throw new RuntimeException(
                    'Nominal melebihi dana yang bisa dikembalikan (' . $this->money($refundable) . ').'
                );
            }

            $refund = BookingRefund::create([
                'booking_id' => $locked->id,
                'clinic_id' => $locked->clinic_id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'provider_refund_id' => $providerRefundId,
                'proof_path' => $proofPath,
                'reason' => trim($reason),
                'recorded_by' => $userId ?? auth()->id(),
            ]);

            // Status bayar booking harus mengikuti uang yang sudah kembali:
            // `refund_pending` selama masih ada sisa, `refunded` setelah lunas.
            // Ditulis di transaksi yang sama dengan baris refund, sehingga tidak
            // ada jendela waktu di mana refund tercatat tetapi status masih `paid`.
            $locked->syncRefundPaymentStatus();

            // Objek milik pemanggil ikut disegarkan: notifikasi dan audit setelah
            // ini membaca `payment_status` yang baru, bukan nilai basi.
            $booking->setRawAttributes($locked->getAttributes(), true);

            return $refund;
        });
    }

    /** Total yang sudah dikembalikan untuk satu booking. */
    public function refundedTotal(Booking $booking): float
    {
        return $booking->refundedTotal();
    }

    /** Dana yang masih bisa dikembalikan. */
    public function refundableAmount(Booking $booking): float
    {
        return $booking->refundableAmount();
    }

    private function money(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}
