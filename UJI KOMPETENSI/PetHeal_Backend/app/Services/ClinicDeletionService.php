<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\Doctor;
use App\Models\DoctorReview;
use App\Models\DoctorScheduleException;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\WeightRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Hapus klinik beserta SELURUH datanya.
 *
 * Skema foreign key di proyek ini tidak seragam: doctors/services/bookings
 * memakai cascade, sedangkan users, medical_records, payment_events,
 * notifications, audit_logs, dan clinic_join_requests memakai nullOnDelete.
 * Kalau hanya baris `clinics` yang dihapus, rekam medis, pembayaran, dan
 * permintaan join akan tertinggal sebagai data yatim. Karena itu setiap tabel
 * dibersihkan eksplisit dari anak ke induk di dalam satu transaksi.
 */
class ClinicDeletionService
{
    /**
     * @return array<string,int> ringkasan jumlah baris yang terhapus
     */
    public function delete(Clinic $clinic): array
    {
        $clinicId = (int) $clinic->id;
        $clinicName = $clinic->name;
        $clinicSlug = $clinic->slug;

        [$counts, $files] = DB::transaction(function () use ($clinicId, $clinicName, $clinicSlug) {
            // Kunci baris klinik supaya dua request hapus bersamaan tidak
            // menjalankan purge dua kali.
            if (!Clinic::whereKey($clinicId)->lockForUpdate()->exists()) {
                abort(404);
            }

            $userIds = User::where('clinic_id', $clinicId)->pluck('id')->all();
            $emails = $userIds === []
                ? []
                : User::whereIn('id', $userIds)->pluck('email')->all();
            $doctorIds = Doctor::where('clinic_id', $clinicId)->pluck('id')->all();
            $bookingIds = Booking::where('clinic_id', $clinicId)->pluck('id')->all();
            $petIds = $userIds === []
                ? []
                : Pet::whereIn('user_id', $userIds)->pluck('id')->all();

            $recordIds = MedicalRecord::where('clinic_id', $clinicId)
                ->when($bookingIds !== [], fn ($query) => $query->orWhereIn('booking_id', $bookingIds))
                ->pluck('id')
                ->all();

            // Kumpulkan file SEBELUM baris database dihapus; kalau tidak,
            // logo/foto akan tertinggal di disk public selamanya.
            $files = $this->collectFiles($clinicId, $userIds, $doctorIds, $petIds);

            // 1. Event pembayaran (booking & rekam medis).
            $paymentEvents = DB::table('payment_events')
                ->where('clinic_id', $clinicId)
                ->delete();
            if ($bookingIds !== []) {
                $paymentEvents += DB::table('payment_events')
                    ->whereIn('booking_id', $bookingIds)
                    ->delete();
            }
            if ($recordIds !== []) {
                $paymentEvents += DB::table('payment_events')
                    ->whereIn('medical_record_id', $recordIds)
                    ->delete();
            }

            // 2. Ulasan dokter.
            $reviews = 0;
            if ($doctorIds !== []) {
                $reviews += DoctorReview::whereIn('doctor_id', $doctorIds)->delete();
            }
            if ($bookingIds !== []) {
                $reviews += DoctorReview::whereIn('booking_id', $bookingIds)->delete();
            }
            if ($userIds !== []) {
                $reviews += DoctorReview::whereIn('user_id', $userIds)->delete();
            }

            // 3. Rekam medis (anak dari booking, dokter, dan hewan).
            $medicalRecords = MedicalRecord::where('clinic_id', $clinicId)->delete();
            if ($bookingIds !== []) {
                $medicalRecords += MedicalRecord::whereIn('booking_id', $bookingIds)->delete();
            }
            if ($petIds !== []) {
                $medicalRecords += MedicalRecord::whereIn('pet_id', $petIds)->delete();
            }

            // 4. Booking.
            $bookings = Booking::where('clinic_id', $clinicId)->delete();
            if ($userIds !== []) {
                $bookings += Booking::whereIn('user_id', $userIds)->delete();
            }
            if ($doctorIds !== []) {
                $bookings += Booking::whereIn('doctor_id', $doctorIds)->delete();
            }

            // 5. Data anak hewan, lalu hewannya.
            $vaccinations = $petIds === []
                ? 0
                : Vaccination::whereIn('pet_id', $petIds)->delete();
            $weightRecords = $petIds === []
                ? 0
                : WeightRecord::whereIn('pet_id', $petIds)->delete();
            $pets = $userIds === []
                ? 0
                : Pet::whereIn('user_id', $userIds)->delete();

            // 6. Pengecualian jadwal, dokter, lalu layanan.
            $scheduleExceptions = DoctorScheduleException::where('clinic_id', $clinicId)->delete();
            if ($doctorIds !== []) {
                $scheduleExceptions += DoctorScheduleException::whereIn('doctor_id', $doctorIds)->delete();
            }
            $doctors = Doctor::where('clinic_id', $clinicId)->delete();
            $services = Service::where('clinic_id', $clinicId)->delete();

            // 7. Notifikasi & kredensial sesi milik akun klinik. Token login
            //    dan OTP tidak punya foreign key, jadi harus dibersihkan
            //    manual supaya tidak tersisa sebagai baris yatim.
            $notifications = Notification::where('clinic_id', $clinicId)->delete();
            $deviceTokens = 0;
            $tokens = 0;
            $sessions = 0;
            if ($userIds !== []) {
                $notifications += Notification::whereIn('user_id', $userIds)->delete();
                $deviceTokens = DB::table('device_tokens')->whereIn('user_id', $userIds)->delete();
                $tokens = DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
                $sessions = DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }
            if ($emails !== []) {
                DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
                DB::table('otp_codes')->whereIn('email', $emails)->delete();
            }

            // 8. Permintaan gabung/registrasi yang menunjuk klinik ini.
            $joinRequests = ClinicJoinRequest::where('clinic_id', $clinicId)->delete();

            // 9. Akun staf & pasien klinik (setelah dokter dilepas supaya FK
            //    doctors.user_id tidak menahan penghapusan).
            $users = $userIds === [] ? 0 : User::whereIn('id', $userIds)->delete();

            // 10. Baris klinik paling akhir. FK nullOnDelete akan melepas
            //     audit_logs yang tersisa agar riwayat super admin tetap ada.
            $deletedClinic = Clinic::whereKey($clinicId)->delete();
            if ($deletedClinic !== 1) {
                throw new RuntimeException('Klinik gagal dihapus: baris klinik masih ada.');
            }

            // Jejak audit global (clinic_id NULL) — sengaja ditulis setelah
            // baris klinik hilang supaya tidak ikut ter-null-kan.
            AuditLog::create([
                'user_id' => auth()->id(),
                'clinic_id' => null,
                'action' => 'clinic.delete',
                'model_type' => Clinic::class,
                'model_id' => $clinicId,
                'description' => "Deleted clinic #{$clinicId} ({$clinicName} / {$clinicSlug}) and all of its data.",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [[
                'users' => $users,
                'doctors' => $doctors,
                'services' => $services,
                'bookings' => $bookings,
                'medical_records' => $medicalRecords,
                'payment_events' => $paymentEvents,
                'notifications' => $notifications,
                'join_requests' => $joinRequests,
                'pets' => $pets,
                'vaccinations' => $vaccinations,
                'weight_records' => $weightRecords,
                'reviews' => $reviews,
                'schedule_exceptions' => $scheduleExceptions,
                'device_tokens' => $deviceTokens,
                'tokens' => $tokens,
                'sessions' => $sessions,
            ], $files];
        });

        // File dihapus SETELAH transaksi commit: kegagalan I/O tidak boleh
        // membatalkan penghapusan data yang sudah sah.
        if ($files !== []) {
            try {
                Storage::disk('public')->delete($files);
            } catch (\Throwable $e) {
                Log::warning('Gagal menghapus file klinik setelah purge.', [
                    'clinic_id' => $clinicId,
                    'files' => $files,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $counts;
    }

    /**
     * @param  array<int,int>  $userIds
     * @param  array<int,int>  $doctorIds
     * @param  array<int,int>  $petIds
     * @return array<int,string>
     */
    private function collectFiles(int $clinicId, array $userIds, array $doctorIds, array $petIds): array
    {
        $paths = [];

        $clinicLogo = Clinic::whereKey($clinicId)->value('logo_path');
        if ($clinicLogo) {
            $paths[] = $clinicLogo;
        }

        if ($doctorIds !== []) {
            $paths = array_merge(
                $paths,
                Doctor::whereIn('id', $doctorIds)->whereNotNull('photo')->pluck('photo')->all()
            );
        }

        if ($userIds !== []) {
            $paths = array_merge(
                $paths,
                User::whereIn('id', $userIds)->whereNotNull('photo')->pluck('photo')->all()
            );
        }

        if ($petIds !== []) {
            $paths = array_merge(
                $paths,
                Pet::whereIn('id', $petIds)->whereNotNull('photo')->pluck('photo')->all()
            );
        }

        return array_values(array_unique(array_filter(
            $paths,
            fn ($path) => is_string($path)
                && $path !== ''
                && !filter_var($path, FILTER_VALIDATE_URL)
        )));
    }
}
