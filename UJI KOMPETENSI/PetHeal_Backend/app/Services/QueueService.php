<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Antrian harian klinik.
 *
 * Aturan yang dijaga:
 *  1. Nomor antrian unik per (klinik, tanggal). Diambil di dalam transaksi
 *     dengan lock, jadi dua pasien check-in bersamaan tidak dapat nomor sama.
 *  2. Nomor urut mengikuti URUTAN CHECK-IN, bukan jam booking. Pasien yang
 *     datang lebih dulu dilayani lebih dulu; jam booking tetap jadi acuan
 *     jadwal dokter, bukan acuan antrian.
 *  3. Antrian tidak pernah menghapus booking. `no_show` hanya menandai
 *     ketidakhadiran dan tetap muncul di papan untuk ditindaklanjuti.
 *  4. Hanya booking terkonfirmasi (confirmed) yang boleh check-in; booking
 *     yang belum dikonfirmasi atau sudah dibatalkan ditolak dengan alasan
 *     yang jelas, bukan diam-diam diabaikan.
 */
class QueueService
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_CALLED = 'called';
    public const STATUS_IN_SERVICE = 'in_service';
    public const STATUS_DONE = 'done';
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_CALLED,
        self::STATUS_IN_SERVICE,
        self::STATUS_DONE,
        self::STATUS_NO_SHOW,
    ];

    /** Status yang masih dianggap "ada di antrian". */
    public const ACTIVE_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_CALLED,
        self::STATUS_IN_SERVICE,
    ];

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_WAITING => 'Menunggu',
            self::STATUS_CALLED => 'Dipanggil',
            self::STATUS_IN_SERVICE => 'Sedang ditangani',
            self::STATUS_DONE => 'Selesai',
            self::STATUS_NO_SHOW => 'Tidak datang',
            default => 'Belum check-in',
        };
    }

    /**
     * Beri nomor antrian untuk booking hari ini.
     *
     * @throws RuntimeException bila booking tidak memenuhi syarat.
     */
    public function checkIn(Booking $booking, ?string $date = null, ?string $userId = null): Booking
    {
        $queueDate = $date ?: today()->toDateString();

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(function () use ($booking, $queueDate, $userId) {
                    $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

                    if ($locked->status === 'cancelled') {
                        throw new RuntimeException('Booking yang dibatalkan tidak bisa masuk antrian.');
                    }

                    if ($locked->status === 'pending') {
                        throw new RuntimeException('Konfirmasi booking terlebih dahulu sebelum pasien check-in.');
                    }

                    // Idempoten: pasien yang sudah punya nomor TIDAK diberi nomor baru.
                    // `queue_date` adalah cast tanggal (objek Carbon), jadi harus
                    // dibandingkan sebagai string — membandingkan objek dengan string
                    // selalu false dan membuat check-in ganda memberi nomor baru.
                    $existingDate = $locked->queue_date?->toDateString();
                    if ($locked->queue_number && $existingDate === $queueDate) {
                        return $locked;
                    }

                    $lastNumber = (int) Booking::where('clinic_id', $locked->clinic_id)
                        ->whereDate('queue_date', $queueDate)
                        ->lockForUpdate()
                        ->max('queue_number');

                    $locked->forceFill([
                        'queue_date' => $queueDate,
                        'queue_number' => $lastNumber + 1,
                        'queue_status' => self::STATUS_WAITING,
                        'checked_in_at' => now(),
                        'called_at' => null,
                        'queue_finished_at' => null,
                    ])->save();

                    // Jejak siapa yang memicu check-in. Admin punya log sendiri
                    // di controller, jadi hanya aksi pasien (API) dicatat di
                    // sini supaya tidak dobel.
                    if ($userId) {
                        AuditLog::log(
                            'queue.check_in.patient',
                            "Pasien check-in booking #{$locked->id} — nomor {$locked->queue_number}",
                            $locked,
                            (int) $userId
                        );
                    }

                    return $locked;
                });
            } catch (QueryException $e) {
                // Nomor unik dijaga unique index (clinic_id, queue_date,
                // queue_number). Dua pasien yang check-in hampir bersamaan
                // bisa gagal di INSERT; ulangi sebentar alih-alih 500.
                if ($attempt >= 3 || (string) $e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
    }

    /**
     * Pindahkan status antrian satu booking.
     *
     * @throws RuntimeException bila transisi tidak masuk akal.
     */
    public function transition(Booking $booking, string $status): Booking
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException("Status antrian tidak dikenal: {$status}");
        }

        return DB::transaction(function () use ($booking, $status) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (!$locked->queue_number) {
                throw new RuntimeException('Pasien belum check-in, belum ada antrian yang bisa diubah.');
            }

            $payload = ['queue_status' => $status];

            if ($status === self::STATUS_CALLED) {
                $payload['called_at'] = now();
            }

            if (in_array($status, [self::STATUS_DONE, self::STATUS_NO_SHOW], true)) {
                $payload['queue_finished_at'] = now();
            }

            $locked->forceFill($payload)->save();

            // `status` booking TIDAK diubah di sini. Antrian hanya mencatat
            // kehadiran fisik; booking ditandai `completed` saat dokter
            // menyimpan rekam medis. Dua sumbu ini sengaja dipisah supaya
            // laporan administrasi tidak berubah arti.

            return $locked;
        });
    }

    /** Panggil antrian berikutnya (paling awal menunggu). */
    public function callNext(int $clinicId, ?string $date = null): ?Booking
    {
        $queueDate = $date ?: today()->toDateString();

        $next = Booking::where('clinic_id', $clinicId)
            ->whereDate('queue_date', $queueDate)
            ->where('queue_status', self::STATUS_WAITING)
            ->orderBy('queue_number')
            ->first();

        return $next ? $this->transition($next, self::STATUS_CALLED) : null;
    }

    /**
     * Papan antrian satu hari, dikelompokkan untuk tampilan.
     *
     * @return array{waiting: Collection, serving: Collection, finished: Collection, stats: array}
     */
    public function board(int $clinicId, ?string $date = null): array
    {
        $queueDate = $date ?: today()->toDateString();

        $bookings = Booking::with(['pet', 'doctor', 'user'])
            ->where('clinic_id', $clinicId)
            ->whereDate('queue_date', $queueDate)
            ->whereNotNull('queue_number')
            ->orderBy('queue_number')
            ->get();

        $waiting = $bookings->whereIn('queue_status', [self::STATUS_WAITING, self::STATUS_CALLED])->values();
        $serving = $bookings->where('queue_status', self::STATUS_IN_SERVICE)->values();
        $finished = $bookings
            ->whereIn('queue_status', [self::STATUS_DONE, self::STATUS_NO_SHOW])
            ->sortByDesc('queue_finished_at')
            ->values();

        return [
            'waiting' => $waiting,
            'serving' => $serving,
            'finished' => $finished,
            'stats' => [
                'total' => $bookings->count(),
                'waiting' => $waiting->count(),
                'serving' => $serving->count(),
                'done' => $bookings->where('queue_status', self::STATUS_DONE)->count(),
                'no_show' => $bookings->where('queue_status', self::STATUS_NO_SHOW)->count(),
            ],
        ];
    }

    /**
     * Posisi tunggu seorang pasien: berapa antrian di depannya (nomor lebih
     * kecil yang statusnya masih menunggu/dipanggil).
     *
     * Null bila pasien belum check-in atau antriannya sudah selesai — UI
     * menampilkan status, bukan angka posisi yang menyesatkan.
     */
    public function positionOf(Booking $booking): ?int
    {
        if (!$booking->queue_number || !$booking->queue_date) {
            return null;
        }

        if (!in_array($booking->queue_status, self::ACTIVE_STATUSES, true)) {
            return null;
        }

        return Booking::where('clinic_id', $booking->clinic_id)
            ->whereDate('queue_date', $booking->queue_date)
            ->whereIn('queue_status', [self::STATUS_WAITING, self::STATUS_CALLED])
            ->where('queue_number', '<', $booking->queue_number)
            ->count();
    }
}
