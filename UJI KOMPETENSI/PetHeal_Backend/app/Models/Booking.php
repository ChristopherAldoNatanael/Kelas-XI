<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    /**
     * Booking batal yang uangnya belum seluruhnya dikembalikan. Nilai ini
     * disimpan di kolom `payment_status` (varchar, bukan enum), jadi tidak perlu
     * migrasi.
     */
    public const PAYMENT_REFUND_PENDING = 'refund_pending';

    /** Booking batal dan seluruh uang yang pernah masuk sudah dikembalikan. */
    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'pet_id',
        'doctor_id',
        'service_id',
        'booking_date',
        'booking_time',
        'status',
        'notes',
        'payment_method',
        'payment_type',
        'dp_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'payment_status',
        'payment_date',
        'cancellation_reason',
        'confirmed_at',
        'completed_at',
        'service_type',
        // Tautan administratif ke rekam medis yang merekomendasikan kontrol.
        'medical_record_id',
        'clinic_id',
        'last_reminder_at',
        // Antrian harian (lihat QueueService). Dipisah dari `status` karena
        // administrasi booking dan kehadiran fisik adalah dua hal berbeda.
        'queue_number',
        'queue_date',
        'queue_status',
        'checked_in_at',
        'called_at',
        'queue_finished_at',
        'queue_note',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'dp_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'last_reminder_at' => 'datetime',
        'queue_number' => 'integer',
        'queue_date' => 'date:Y-m-d',
        'checked_in_at' => 'datetime',
        'called_at' => 'datetime',
        'queue_finished_at' => 'datetime',
    ];

    /** Sudah punya nomor antrian hari ini? */
    public function hasQueueToday(): bool
    {
        return $this->queue_number !== null
            && $this->queue_date?->toDateString() === today()->toDateString();
    }

    /**
     * Always return booking_time as a plain "HH:mm" string.
     */
    public function getBookingTimeAttribute($value): ?string
    {
        if (!$value) return null;
        // Already "HH:mm" or "HH:mm:ss"
        return substr($value, 0, 5);
    }

    /**
     * Always return booking_date as "YYYY-MM-DD" string for Android.
     */
    public function getBookingDateAttribute($value): ?string
    {
        if (!$value) return null;
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return substr((string) $value, 0, 10);
    }

    /**
     * Get formatted booking date for display (e.g., "Monday, 06 April 2026").
     */
    public function getFormattedBookingDateAttribute(): ?string
    {
        $date = $this->attributes['booking_date'] ?? null;
        if (!$date) return null;

        if ($date instanceof \Carbon\Carbon) {
            return $date->isoFormat('dddd, DD MMMM YYYY');
        }

        try {
            return \Carbon\Carbon::parse($date)->isoFormat('dddd, DD MMMM YYYY');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the clinic this booking belongs to
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get the user that made the booking
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pet for this booking
     */
    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the doctor for this booking
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the service for this booking
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Catatan pengembalian uang (bisa lebih dari satu kali).
     */
    public function refunds()
    {
        return $this->hasMany(BookingRefund::class);
    }

    /**
     * Total uang yang SUDAH dikembalikan ke pasien.
     */
    public function refundedTotal(): float
    {
        return (float) $this->refunds()->sum('amount');
    }

    /**
     * Dana yang masih wajib dikembalikan (tidak pernah negatif).
     */
    public function refundableAmount(): float
    {
        return max(0, (float) $this->paid_amount - $this->refundedTotal());
    }

    /**
     * Pembayaran online (Midtrans) milik booking ini yang masih punya sisa dana.
     *
     * Dipakai untuk dua hal yang harus selalu sepakat: menampilkan pilihan
     * "Kembalikan via Midtrans" di halaman booking, dan memilih transaksi mana
     * yang diminta refund-nya saat admin menekan tombol itu.
     */
    public function refundableOnlinePayment(): ?\App\Models\Payment
    {
        return \App\Models\Payment::query()
            ->where('payable_type', self::class)
            ->where('payable_id', $this->id)
            ->where('provider', 'midtrans')
            ->whereIn('status', [
                \App\Models\Payment::STATUS_PAID,
                \App\Models\Payment::STATUS_PARTIALLY_REFUNDED,
            ])
            ->orderByDesc('paid_at')
            ->get()
            ->first(fn (\App\Models\Payment $payment) =>
                (int) $payment->gross_amount > (int) $payment->refunded_amount);
    }

    /**
     * Nilai `payment_status` yang benar untuk sebuah booking batal berdasarkan
     * uang masuk dan refund yang sudah dicatat.
     *
     * Satu-satunya tempat aturan ini ditulis. Dipakai oleh pembatalan (admin &
     * aplikasi), pencatatan refund, dan webhook yang masuk setelah booking
     * dibatalkan — supaya tidak ada dua definisi "sudah direfund" yang bisa
     * berbeda hasilnya.
     */
    public static function refundPaymentStatusFor(mixed $paidAmount, mixed $refundedAmount): ?string
    {
        $paid = (int) round((float) $paidAmount);
        $refunded = (int) round((float) $refundedAmount);

        if ($paid <= 0) {
            return null;
        }

        if ($refunded >= $paid) {
            return self::PAYMENT_REFUNDED;
        }

        return self::PAYMENT_REFUND_PENDING;
    }

    /**
     * Selaraskan `payment_status` booking yang dibatalkan dengan uang yang
     * sudah masuk dan refund yang sudah dicatat.
     *
     * Sebelumnya pembatalan hanya menulis `status = cancelled`; `payment_status`
     * tetap `paid`/`dp_paid` sehingga laporan terlihat seperti uang itu pendapatan
     * yang sah dan kewajiban refund tidak pernah muncul di layar mana pun.
     *
     * @return string|null nilai baru, atau null bila booking tidak butuh status refund
     */
    public function syncRefundPaymentStatus(): ?string
    {
        if ($this->status !== 'cancelled') {
            return null;
        }

        $target = self::refundPaymentStatusFor($this->paid_amount, $this->refundedTotal());

        if ($target === null) {
            return null;
        }

        if ($this->payment_status !== $target) {
            $this->forceFill(['payment_status' => $target])->save();
        }

        return $target;
    }

    /** Booking kontrol: rekam medis asal rekomendasi. */
    public function sourceMedicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    /**
     * Get the medical record for this booking
     */
    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /**
     * Scope for pending bookings
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for today's bookings
     */
    public function scopeToday($query)
    {
        return $query->whereDate('booking_date', today());
    }

    /**
     * Scope for upcoming bookings
     */
    public function scopeUpcoming($query)
    {
        return $query->whereDate('booking_date', '>=', today())
            ->whereIn('status', ['pending', 'confirmed']);
    }
}
