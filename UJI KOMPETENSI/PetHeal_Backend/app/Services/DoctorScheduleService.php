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
     * Toleransi beberapa menit untuk slot yang baru saja lewat.
     *
     * Pasien yang membuka form pukul 09.59 lalu menekan "Booking" pukul 10.01
     * tidak boleh ditolak hanya karena jendela satu menit. Di luar toleransi
     * ini, slot dianggap sudah lewat.
     */
    public const PAST_SLOT_GRACE_MINUTES = 5;

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
     * Jadwal efektif satu tanggal: irisan jadwal dokter dan jam operasional
     * klinik.
     *
     * Klinik yang belum mengisi jam operasional tidak membatasi apa pun
     * (perilaku lama tetap jalan). Bila klinik tutup pada hari itu, tidak ada
     * slot meskipun dokter punya jadwal mingguan.
     *
     * @return array{is_working_day:bool,is_closed:bool,has_exception:bool,start_time:?string,end_time:?string,reason:?string,source:string,clinic_is_open:?bool,clinic_start_time:?string,clinic_end_time:?string,clinic_closed:bool,outside_clinic_hours:bool}
     */
    public function effectiveSchedule(Doctor $doctor, string $date): array
    {
        $exception = $this->exceptionFor($doctor, $date);

        if ($exception && $exception->is_closed) {
            return $this->applyClinicHours($doctor, $date, [
                'is_working_day' => false,
                'is_closed' => true,
                'has_exception' => true,
                'start_time' => null,
                'end_time' => null,
                'reason' => $exception->reason,
                'source' => 'exception_closed',
            ]);
        }

        if ($exception) {
            $start = $exception->start_time ?: $doctor->start_time;
            $end = $exception->end_time ?: $doctor->end_time;

            return $this->applyClinicHours($doctor, $date, [
                'is_working_day' => $this->isValidWindow($start, $end),
                'is_closed' => false,
                'has_exception' => true,
                'start_time' => self::normalizeTime($start),
                'end_time' => self::normalizeTime($end),
                'reason' => $exception->reason,
                'source' => 'exception_custom',
            ]);
        }

        $worksWeekly = $this->worksOnWeekday($doctor, $date);

        return $this->applyClinicHours($doctor, $date, [
            'is_working_day' => $worksWeekly,
            'is_closed' => false,
            'has_exception' => false,
            'start_time' => $worksWeekly ? self::normalizeTime($doctor->start_time) : null,
            'end_time' => $worksWeekly ? self::normalizeTime($doctor->end_time) : null,
            'reason' => null,
            'source' => 'weekly',
        ]);
    }

    /**
     * Jam operasional klinik pada satu tanggal.
     *
     * `null` berarti klinik belum mengisi jam operasional, sehingga jadwal
     * dokter dipakai apa adanya.
     *
     * @return array{is_open:bool,start_time:?string,end_time:?string,reason:?string}|null
     */
    public function clinicWindow(Doctor $doctor, string $date): ?array
    {
        $clinic = $doctor->clinic;

        if (!$clinic || !$clinic->opening_time || !$clinic->closing_time) {
            return null;
        }

        $dayName = strtolower(Carbon::parse($date)->format('l'));
        $rawDays = $clinic->operating_days;

        if (is_string($rawDays)) {
            $rawDays = array_filter(array_map('trim', explode(',', $rawDays)));
        }

        $days = is_array($rawDays)
            ? array_map(fn ($day) => strtolower((string) $day), $rawDays)
            : [];

        if ($days !== [] && !in_array('all', $days, true) && !in_array($dayName, $days, true)) {
            return [
                'is_open' => false,
                'start_time' => null,
                'end_time' => null,
                'reason' => 'Klinik tutup pada hari ' . $this->dayLabel($dayName) . '.',
            ];
        }

        $start = self::normalizeTime($clinic->opening_time);
        $end = self::normalizeTime($clinic->closing_time);

        if (!$this->isValidWindow($start, $end)) {
            return null;
        }

        return [
            'is_open' => true,
            'start_time' => $start,
            'end_time' => $end,
            'reason' => null,
        ];
    }

    /**
     * Terapkan jam klinik ke jadwal dokter: tutup = tidak ada slot, buka =
     * ambil irisan jam praktik dokter dengan jam operasional klinik.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    private function applyClinicHours(Doctor $doctor, string $date, array $schedule): array
    {
        $window = $this->clinicWindow($doctor, $date);

        $schedule['clinic_is_open'] = $window['is_open'] ?? null;
        $schedule['clinic_start_time'] = $window['start_time'] ?? null;
        $schedule['clinic_end_time'] = $window['end_time'] ?? null;
        $schedule['clinic_closed'] = $window !== null && !$window['is_open'];
        $schedule['outside_clinic_hours'] = false;

        if ($window === null || !$schedule['is_working_day']) {
            return $schedule;
        }

        if (!$window['is_open']) {
            return array_merge($schedule, [
                'is_working_day' => false,
                'is_closed' => false,
                'start_time' => null,
                'end_time' => null,
                'reason' => $window['reason'],
            ]);
        }

        $doctorStart = $this->minutes($schedule['start_time']);
        $doctorEnd = $this->minutes($schedule['end_time']);
        $clinicStart = $this->minutes($window['start_time']);
        $clinicEnd = $this->minutes($window['end_time']);

        if ($doctorStart === null || $doctorEnd === null || $clinicStart === null || $clinicEnd === null) {
            return $schedule;
        }

        $start = max($doctorStart, $clinicStart);
        $end = min($doctorEnd, $clinicEnd);

        if ($end <= $start) {
            return array_merge($schedule, [
                'is_working_day' => false,
                'start_time' => null,
                'end_time' => null,
                'outside_clinic_hours' => true,
                'reason' => sprintf(
                    'Jam praktik dokter (%s-%s) tidak beririsan dengan jam operasional klinik (%s-%s).',
                    $schedule['start_time'],
                    $schedule['end_time'],
                    $window['start_time'],
                    $window['end_time']
                ),
            ]);
        }

        return array_merge($schedule, [
            'start_time' => sprintf('%02d:%02d', intdiv($start, 60), $start % 60),
            'end_time' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
        ]);
    }

    /** Nama hari untuk pesan yang dibaca admin/pemilik hewan. */
    private function dayLabel(string $day): string
    {
        return [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
        ][$day] ?? ucfirst($day);
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
        $now = Carbon::now();
        $isToday = Carbon::parse($date)->isSameDay($now);

        for ($minute = $start; $minute < $end; $minute += self::SLOT_INTERVAL_MINUTES) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            $taken = in_array($time, $booked, true);

            // Slot hari ini yang jamnya sudah lewat tidak ditawarkan lagi.
            // Penandaan di sini membuat aplikasi menyembunyikan/menonaktifkan
            // slot tersebut sebelum pasien memilih.
            $past = $isToday
                && Carbon::parse($date . ' ' . $time)
                    ->addMinutes(self::PAST_SLOT_GRACE_MINUTES)
                    ->isPast();

            $slots[] = [
                'time' => $time,
                'available' => !$taken && !$past,
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
            $reason = trim((string) ($schedule['reason'] ?? ''));

            if ($reason !== '' && (!empty($schedule['clinic_closed']) || !empty($schedule['outside_clinic_hours']))) {
                return $reason . ' Silakan pilih tanggal atau jam lain.';
            }

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
                'Jam %s di luar jam layanan dokter/klinik (%s - %s).',
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

        // Slot yang sudah lewat tidak boleh dibooking. Sebelum ini, penjagaan
        // hanya ada di aplikasi sehingga request yang dimanipulasi (atau form
        // yang dibiarkan terbuka lama) tetap bisa membuat booking untuk jam
        // yang sudah lewat.
        $slotMoment = Carbon::parse($date . ' ' . substr($time, 0, 5));
        if ($slotMoment->copy()->addMinutes(self::PAST_SLOT_GRACE_MINUTES)->isPast()) {
            return sprintf(
                'Jam %s pada %s sudah lewat. Silakan pilih jam atau tanggal lain.',
                substr($time, 0, 5),
                $slotMoment->translatedFormat('d F Y')
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

    /**
     * Bentuk baku jam `H:i` untuk semua yang disimpan/dibandingkan.
     *
     * Kolom `time` di MySQL menyimpan "09:00:00", sedangkan validasi form dan
     * slot booking memakai "H:i". Nilai yang lebih panjang dipotong supaya
     * menyimpan ulang jam yang sama tidak ditolak `date_format:H:i`.
     */
    public static function normalizeTime(?string $time): ?string
    {
        if (!$time) return null;
        $trimmed = trim($time);
        if ($trimmed === '') return null;
        return substr($trimmed, 0, 5);
    }

    private function minutes(?string $time): ?int
    {
        $normalized = self::normalizeTime($time);
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
