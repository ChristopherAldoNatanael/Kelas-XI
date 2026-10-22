<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\DoctorScheduleException;
use Carbon\Carbon;

/**
 * Satu sumber kebenaran untuk jadwal efektif dokter.
 *
 * Dipakai oleh endpoint slot, pembuatan booking, reschedule, dan papan jadwal
 * admin supaya cuti/jam khusus tidak pernah dihitung berbeda antar layar.
 *
 * Aturan:
 * 1. Pengecualian `is_closed` selalu menang → tidak ada slot.
 * 2. Pengecualian dengan jam khusus menimpa jam mingguan (dan boleh membuka
 *    hari yang biasanya tutup, mis. Sabtu tambahan).
 * 3. Tanpa pengecualian → available_days + start_time/end_time seperti biasa.
 */
class DoctorScheduleService
{
    public const SLOT_INTERVAL_MINUTES = 30;

    /**
     * Pengecualian aktif untuk dokter pada tanggal tertentu (clinic-scoped).
     */
    public function exceptionFor(Doctor $doctor, string $date): ?DoctorScheduleException
    {
        return DoctorScheduleException::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->first();
    }

    /**
     * Jadwal efektif satu tanggal.
     *
     * @return array{is_working_day:bool,is_closed:bool,has_exception:bool,start_time:?string,end_time:?string,reason:?string,source:string}
     */
    public function effectiveSchedule(Doctor $doctor, string $date): array
    {
        $exception = $this->exceptionFor($doctor, $date);

        if ($exception && $exception->is_closed) {
            return [
                'is_working_day' => false,
                'is_closed' => true,
                'has_exception' => true,
                'start_time' => null,
                'end_time' => null,
                'reason' => $exception->reason,
                'source' => 'exception_closed',
            ];
        }

        if ($exception) {
            $start = $exception->start_time ?: $doctor->start_time;
            $end = $exception->end_time ?: $doctor->end_time;

            return [
                'is_working_day' => $this->isValidWindow($start, $end),
                'is_closed' => false,
                'has_exception' => true,
                'start_time' => $this->normalizeTime($start),
                'end_time' => $this->normalizeTime($end),
                'reason' => $exception->reason,
                'source' => 'exception_custom',
            ];
        }

        $worksWeekly = $this->worksOnWeekday($doctor, $date);

        return [
            'is_working_day' => $worksWeekly,
            'is_closed' => false,
            'has_exception' => false,
            'start_time' => $worksWeekly ? $this->normalizeTime($doctor->start_time) : null,
            'end_time' => $worksWeekly ? $this->normalizeTime($doctor->end_time) : null,
            'reason' => null,
            'source' => 'weekly',
        ];
    }

    /**
     * Daftar slot (flat, siap dikirim ke Android) sesuai jadwal efektif.
     *
     * @return array<int,array{time:string,available:bool}>
     */
    public function slotsFor(Doctor $doctor, string $date, ?array $schedule = null): array
    {
        $schedule ??= $this->effectiveSchedule($doctor, $date);
        if (!$schedule['is_working_day']) {
            return [];
        }

        $start = $this->minutes($schedule['start_time']);
        $end = $this->minutes($schedule['end_time']);
        if ($start === null || $end === null || $end <= $start) {
            return [];
        }

        $booked = \App\Models\Booking::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('booking_date', Carbon::parse($date)->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('booking_time')
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->all();

        $slots = [];
        for ($minute = $start; $minute < $end; $minute += self::SLOT_INTERVAL_MINUTES) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            $slots[] = [
                'time' => $time,
                'available' => !in_array($time, $booked, true),
            ];
        }

        return $slots;
    }

    /**
     * Validasi slot terhadap jadwal efektif.
     *
     * @return string|null Pesan siap tampil, atau null bila slot sah.
     */
    public function validateSlot(Doctor $doctor, string $date, string $time): ?string
    {
        $schedule = $this->effectiveSchedule($doctor, $date);

        if ($schedule['is_closed']) {
            $reason = trim((string) $schedule['reason']);
            return $reason !== ''
                ? "Dokter cuti pada tanggal tersebut ({$reason}). Silakan pilih tanggal lain."
                : 'Dokter cuti pada tanggal tersebut. Silakan pilih tanggal lain.';
        }

        if (!$schedule['is_working_day']) {
            return 'Dokter tidak praktik pada tanggal tersebut. Silakan pilih tanggal lain.';
        }

        $start = $this->minutes($schedule['start_time']);
        $end = $this->minutes($schedule['end_time']);
        $slot = $this->minutes($time);

        if ($start === null || $end === null || $slot === null) {
            return 'Jam yang dipilih tidak valid.';
        }

        if ($slot < $start || $slot >= $end) {
            return sprintf(
                'Jam %s di luar jadwal praktik dokter (%s - %s).',
                substr($time, 0, 5),
                $schedule['start_time'],
                $schedule['end_time']
            );
        }

        if (($slot - $start) % self::SLOT_INTERVAL_MINUTES !== 0) {
            return sprintf(
                'Jam %s bukan slot yang tersedia. Pilih salah satu jam pada jadwal.',
                substr($time, 0, 5)
            );
        }

        return null;
    }

    /**
     * Apakah jam tertentu berada di dalam jadwal efektif (tanpa menilai
     * kelipatan slot). Dipakai papan jadwal admin untuk menandai booking lama.
     */
    public function isTimeWithinSchedule(array $schedule, ?string $time): bool
    {
        if (empty($schedule['is_working_day'])) {
            return false;
        }

        $start = $this->minutes($schedule['start_time'] ?? null);
        $end = $this->minutes($schedule['end_time'] ?? null);
        $slot = $this->minutes($time);

        return $start !== null && $end !== null && $slot !== null
            && $slot >= $start && $slot < $end;
    }

    /**
     * Booking aktif yang jatuh di luar jadwal efektif (dipakai admin saat
     * menandai cuti). Booking TIDAK dibatalkan — hanya ditandai untuk ditinjau.
     */
    public function bookingsOutsideSchedule(Doctor $doctor, string $date): \Illuminate\Support\Collection
    {
        $schedule = $this->effectiveSchedule($doctor, $date);
        $bookings = \App\Models\Booking::with(['pet', 'user'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('booking_date', Carbon::parse($date)->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_time')
            ->get();

        return $bookings
            ->filter(fn ($booking) => !$this->isTimeWithinSchedule($schedule, (string) $booking->booking_time))
            ->values();
    }

    public function worksOnWeekday(Doctor $doctor, string $date): bool
    {
        $days = $this->availableDays($doctor);
        if ($days === []) {
            return false;
        }

        $dayName = strtolower(Carbon::parse($date)->format('l'));
        return in_array($dayName, $days, true);
    }

    /**
     * `available_days` bisa berupa array (cast) atau string koma.
     *
     * @return array<int,string>
     */
    public function availableDays(Doctor $doctor): array
    {
        $raw = $doctor->available_days;
        $days = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(
            fn ($day) => strtolower(trim((string) $day)),
            $days
        )));
    }

    public function normalizeTime(?string $time): ?string
    {
        if (!$time) return null;
        return substr(trim($time), 0, 5);
    }

    private function minutes(?string $time): ?int
    {
        $normalized = $this->normalizeTime($time);
        if (!$normalized) return null;
        if (!preg_match('/^(\d{2}):(\d{2})$/', $normalized, $matches)) {
            return null;
        }
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour > 23 || $minute > 59) return null;
        return ($hour * 60) + $minute;
    }

    private function isValidWindow(?string $start, ?string $end): bool
    {
        $startMinutes = $this->minutes($start);
        $endMinutes = $this->minutes($end);
        return $startMinutes !== null && $endMinutes !== null && $endMinutes > $startMinutes;
    }
}
