<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alur permintaan layanan → penawaran → booking.
 *
 * Semua perubahan status melewati satu pintu (`advance`) supaya transisi
 * ilegal ditolak dan notifikasi tidak terkirim untuk status yang sama. Tidak
 * ada booking atau pembayaran yang dibuat sebelum pemilik menyetujui
 * penawaran.
 */
class ServiceRequestService
{
    public function __construct(private readonly FCMService $fcm)
    {
    }

    /**
     * Simpan pengajuan baru + kabari pemilik bahwa permintaan diterima.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, Pet $pet, array $data): ServiceRequest
    {
        $request = DB::transaction(function () use ($user, $pet, $data) {
            $request = ServiceRequest::create([
                'code' => 'TMP-' . uniqid(),
                'user_id' => $user->id,
                'pet_id' => $pet->id,
                'clinic_id' => $data['clinic_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'medical_record_id' => $data['medical_record_id'] ?? null,
                'need_type' => $data['need_type'],
                'complaint' => $data['complaint'],
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_time' => $data['preferred_time'] ?? null,
                'status' => ServiceRequest::STATUS_SUBMITTED,
                'needs_follow_up' => $data['need_type'] === 'follow_up',
            ]);

            // Kode dibuat dari id agar unik tanpa penghitung terpisah yang
            // bisa balapan. Placeholder di atas hanya untuk melewati NOT NULL.
            $request->forceFill([
                'code' => 'SR-' . now()->format('Ymd') . '-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT),
            ])->save();

            return $request;
        });

        $request->load(['pet', 'clinic', 'doctor']);
        $this->fcm->sendServiceRequestEvent($user->id, 'submitted', $request);

        return $request;
    }

    /**
     * Ubah status dengan validasi transisi.
     *
     * @param  array<string, mixed>  $attributes
     * @param  bool  $forceNotify  Kirim notifikasi walau status tidak berubah
     *                           (mis. admin meminta informasi tambahan saat
     *                           permintaan masih `under_review`).
     */
    public function advance(
        ServiceRequest $request,
        string $status,
        array $attributes = [],
        ?string $notifyEvent = null,
        bool $forceNotify = false
    ): ServiceRequest {
        if (!in_array($status, ServiceRequest::STATUSES, true)) {
            throw new RuntimeException('Status permintaan layanan tidak dikenal.');
        }

        if ($request->status !== $status && !in_array($status, ServiceRequest::TRANSITIONS[$request->status] ?? [], true)) {
            throw new RuntimeException(
                'Status tidak bisa diubah dari '
                . ServiceRequest::statusLabel($request->status)
                . ' ke ' . ServiceRequest::statusLabel($status) . '.'
            );
        }

        // Status yang sama tidak memicu notifikasi ulang — mencegah push
        // ganda ketika admin membuka form dan submits ulang tanpa perubahan.
        $statusChanged = $request->status !== $status;

        $request->fill($attributes)->fill(['status' => $status])->save();

        if ($notifyEvent && ($statusChanged || $forceNotify)) {
            $request->load(['pet', 'clinic']);
            $this->fcm->sendServiceRequestEvent($request->user_id, $notifyEvent, $request);
        }

        return $request;
    }

    /**
     * Admin/dokter mengirim atau memperbarui penawaran.
     *
     * @param  array<string, mixed>  $offer
     */
    public function offer(ServiceRequest $request, array $offer): ServiceRequest
    {
        if (in_array($request->status, [ServiceRequest::STATUS_CONVERTED, ServiceRequest::STATUS_REJECTED, ServiceRequest::STATUS_CANCELLED], true)) {
            throw new RuntimeException('Permintaan ini sudah ditutup dan tidak bisa diberi penawaran.');
        }

        $isUpdate = $request->status === ServiceRequest::STATUS_OFFERED
            && $request->offered_at !== null;

        $attributes = [
            'status' => ServiceRequest::STATUS_OFFERED,
            'offer_service_id' => $offer['offer_service_id'] ?? null,
            'offer_service_name' => $offer['offer_service_name'],
            'offer_description' => $offer['offer_description'] ?? null,
            'offer_price' => $offer['offer_price'] ?? null,
            'offer_price_type' => $offer['offer_price_type'] ?? 'final',
            'offer_duration_minutes' => $offer['offer_duration_minutes'] ?? null,
            'offer_doctor_id' => $offer['offer_doctor_id'] ?? null,
            'offer_schedule_date' => $offer['offer_schedule_date'] ?? null,
            // Kolom hanya menyimpan HH:mm; API/form bisa mengirim HH:mm:ss.
            'offer_schedule_time' => isset($offer['offer_schedule_time'])
                ? substr((string) $offer['offer_schedule_time'], 0, 5)
                : null,
            'offer_notes' => $offer['offer_notes'] ?? null,
            'offer_valid_until' => $offer['offer_valid_until'] ?? null,
            'offered_at' => now(),
            // Penawaran baru membatalkan keputusan lama.
            'accepted_at' => null,
            'rejected_at' => null,
            'owner_response_note' => null,
        ];

        $request->fill($attributes)->save();

        $request->load(['pet', 'clinic']);
        $this->fcm->sendServiceRequestEvent(
            $request->user_id,
            $isUpdate ? 'offer_updated' : 'offer_created',
            $request
        );

        return $request;
    }

    /**
     * Pemilik menyetujui penawaran → booking `pending` + tautan permintaan.
     *
     * @throws RuntimeException bila penawaran tidak lengkap/kedaluwarsa atau
     *                          slot sudah terisi.
     */
    public function accept(ServiceRequest $request, ?string $note = null): Booking
    {
        $this->expireIfNeeded($request);

        if ($request->status === ServiceRequest::STATUS_EXPIRED) {
            throw new RuntimeException('Penawaran sudah kedaluwarsa. Minta klinik mengirim penawaran baru.');
        }

        $booking = DB::transaction(function () use ($request, $note) {
            $locked = ServiceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ServiceRequest::STATUS_CONVERTED) {
                // Idempoten: persetujuan ganda tidak membuat booking kedua.
                // Booking bisa saja sudah dihapus (kolomnya nullOnDelete),
                // jadi jangan mengembalikan null dari method bertipe Booking.
                if (!$locked->booking) {
                    throw new RuntimeException('Permintaan ini sudah menjadi booking, tetapi booking tersebut sudah tidak ada.');
                }

                return $locked->booking;
            }

            if ($locked->status !== ServiceRequest::STATUS_OFFERED) {
                throw new RuntimeException('Penawaran ini tidak sedang menunggu persetujuan.');
            }

            if ($locked->offer_valid_until && $locked->offer_valid_until->isPast()) {
                // Jangan menulis status di dalam transaksi yang akan rollback;
                // `expireIfNeeded()` di atas sudah menanganinya sebelum lock.
                throw new RuntimeException('Penawaran sudah kedaluwarsa. Minta klinik mengirim penawaran baru.');
            }

            if (!$locked->offer_doctor_id || !$locked->offer_schedule_date || !$locked->offer_schedule_time) {
                throw new RuntimeException('Penawaran belum memiliki dokter dan jadwal. Hubungi klinik.');
            }

            $doctor = Doctor::where('id', $locked->offer_doctor_id)
                ->where('clinic_id', $locked->clinic_id)
                ->where('is_active', true)
                ->first();

            if (!$doctor) {
                throw new RuntimeException('Dokter pada penawaran tidak lagi tersedia di klinik ini.');
            }

            $service = null;
            if ($locked->offer_service_id) {
                $service = Service::where('id', $locked->offer_service_id)
                    ->where('clinic_id', $locked->clinic_id)
                    ->first();
            }

            $slotTaken = Booking::where('doctor_id', $doctor->id)
                ->where('clinic_id', $locked->clinic_id)
                ->whereDate('booking_date', $locked->offer_schedule_date)
                ->where('booking_time', $locked->offer_schedule_time)
                ->whereIn('status', ['pending', 'confirmed'])
                ->lockForUpdate()
                ->exists();

            if ($slotTaken) {
                throw new RuntimeException('Jadwal pada penawaran sudah terisi. Minta klinik memperbarui penawaran.');
            }

            $price = (float) ($locked->offer_price ?? $service?->price ?? 0);

            $booking = Booking::create([
                'user_id' => $locked->user_id,
                'pet_id' => $locked->pet_id,
                'doctor_id' => $doctor->id,
                'service_id' => $service?->id,
                'medical_record_id' => $locked->medical_record_id,
                'clinic_id' => $locked->clinic_id,
                'booking_date' => $locked->offer_schedule_date->toDateString(),
                'booking_time' => $locked->offer_schedule_time,
                'status' => 'pending',
                'notes' => trim(
                    'Booking dari permintaan layanan ' . $locked->code . '.'
                    . ($locked->offer_notes ? ' Catatan klinik: ' . $locked->offer_notes : '')
                ),
                'payment_type' => 'full',
                'total_amount' => $price,
                'dp_amount' => 0,
                'paid_amount' => 0,
                'remaining_amount' => $price,
                'payment_status' => 'pending',
            ]);

            // Satu kali simpan: `accepted` hanyalah status antara di dalam
            // transaksi ini, jadi menulisnya lalu langsung menimpanya dengan
            // `converted_to_booking` hanya menambah UPDATE tanpa jejak.
            $locked->fill([
                'status' => ServiceRequest::STATUS_CONVERTED,
                'booking_id' => $booking->id,
                'accepted_at' => now(),
                'owner_response_note' => $note ? mb_substr(trim($note), 0, 500) : null,
            ])->save();

            return $booking;
        });

        $request->refresh()->load(['pet', 'clinic']);
        $this->fcm->sendServiceRequestEvent($request->user_id, 'converted', $request);

        return $booking;
    }

    /**
     * Pemilik menolak penawaran. Tidak ada booking/pembayaran yang dibuat.
     */
    public function reject(ServiceRequest $request, ?string $note = null): ServiceRequest
    {
        return $this->advance(
            $request,
            ServiceRequest::STATUS_REJECTED,
            [
                'rejected_at' => now(),
                'owner_response_note' => $note ? mb_substr(trim($note), 0, 500) : null,
            ],
            'rejected'
        );
    }

    /**
     * Tandai penawaran kedaluwarsa saat dibaca/diakses. Idempoten: status
     * hanya berubah sekali dan notifikasi hanya dikirim pada perubahan itu.
     */
    public function expireIfNeeded(ServiceRequest $request): ServiceRequest
    {
        if ($request->status === ServiceRequest::STATUS_OFFERED
            && $request->offer_valid_until !== null
            && $request->offer_valid_until->isPast()) {
            $request->fill(['status' => ServiceRequest::STATUS_EXPIRED])->save();
            $request->load(['pet', 'clinic']);
            $this->fcm->sendServiceRequestEvent($request->user_id, 'expired', $request);
        }

        return $request;
    }
}
