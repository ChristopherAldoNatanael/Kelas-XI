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
 *  5. Notifikasi FCM dikirim SETELAH transaksi commit dan hanya saat status
 *     benar-benar berubah. Database tetap sumber kebenaran; FCM hanya kabar.
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

    /**
     * Transisi status yang masuk akal. Panggilan ulang pasien yang dilewati
     * (no_show → called) diizinkan supaya petugas tidak kehilangan pasien
     * yang ternyata masih di klinik.
     */
    public const TRANSITIONS = [
        self::STATUS_WAITING => [self::STATUS_CALLED, self::STATUS_IN_SERVICE, self::STATUS_DONE, self::STATUS_NO_SHOW],
        self::STATUS_CALLED => [self::STATUS_IN_SERVICE, self::STATUS_DONE, self::STATUS_NO_SHOW, self::STATUS_WAITING],
        self::STATUS_IN_SERVICE => [self::STATUS_DONE, self::STATUS_WAITING],
        self::STATUS_NO_SHOW => [self::STATUS_WAITING, self::STATUS_CALLED],
        self::STATUS_DONE => [],
    ];

    public function __construct(private readonly FCMService $fcm)
    {
    }

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

    /** Label nomor antrian yang ditampilkan ke pasien, mis. "A-08". */
    public static function label(?int $number): ?string
    {
        return $number ? 'A-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT) : null;
    }

    public static function canTransition(?string $from, string $to): bool
    {
        if (!in_array($to, self::STATUSES, true)) {
            return false;
        }

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Beri nomor antrian untuk booking hari ini.
     *
     * @throws RuntimeException bila booking tidak memenuhi syarat.
     */
    /**
     * Lepas nomor antrian ketika booking dibatalkan.
     *
     * Dipanggil dari kedua jalur pembatalan (aplikasi & web admin) SETELAH
     * status booking menjadi `cancelled`. Antrian harian tidak berubah
     * historis: nomor tetap tercatat dengan status `no_show` dan catatan
     * sebabnya, sehingga papan "selesai" masih bisa menjelaskan kejadiannya.
     *
     * Aman dipanggil kapan pun: booking yang belum check-in atau yang
     * antriannya sudah selesai tidak diapa-apakan.
     */
    public function releaseOnCancel(Booking $booking, ?string $reason = null): Booking
    {
        if (! $booking->queue_number
            || ! in_array($booking->queue_status, self::ACTIVE_STATUSES, true)) {
            return $booking;
        }

        $note = trim('Booking dibatalkan' . ($reason ? ': ' . $reason : '.'));

        $booking->forceFill([
            'queue_status' => self::STATUS_NO_SHOW,
            'queue_finished_at' => now(),
            'queue_note' => mb_substr($note, 0, 255),
        ])->save();

        return $booking;
    }

    public function checkIn(Booking $booking, ?string $date = null, ?string $userId = null): Booking
    {
        $queueDate = $date ?: today()->toDateString();

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                [$result, $assigned] = DB::transaction(function () use ($booking, $queueDate, $userId) {
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
                        return [$locked, false];
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
                        'queue_note' => null,
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

                    return [$locked, true];
                });
            } catch (QueryException $e) {
                // Nomor unik dijaga unique index (clinic_id, queue_date,
                // queue_number). Dua pasien yang check-in hampir bersamaan
                // bisa gagal di INSERT; ulangi sebentar alih-alih 500.
                if ($attempt >= 3 || (string) $e->getCode() !== '23000') {
                    throw $e;
                }

                continue;
            }

            // Notifikasi dikirim setelah commit dan hanya untuk nomor baru.
            // Check-in berulang tidak mengirim suara/notifikasi kedua.
            if ($assigned) {
                $result->loadMissing(['pet', 'doctor', 'clinic']);
                $this->fcm->sendQueueEvent($result->user_id, 'checked_in', $result);
            }

            return $result;
        }
    }

    /**
     * Pindahkan status antrian satu booking.
     *
     * @throws RuntimeException bila transisi tidak masuk akal.
     */
    public function transition(Booking $booking, string $status, ?string $note = null): Booking
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException("Status antrian tidak dikenal: {$status}");
        }

        $previous = null;
        $recalled = false;

        $result = DB::transaction(function () use ($booking, $status, $note, &$previous, &$recalled) {
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (!$locked->queue_number) {
                throw new RuntimeException('Pasien belum check-in, belum ada antrian yang bisa diubah.');
            }

            $previous = $locked->queue_status;

            if ($previous === $status) {
                // Panggil ulang. Statusnya memang tetap `called`, tetapi ini
                // peristiwa baru: waktu panggil diperbarui supaya identitas
                // peristiwa (`queue_event_id`) berubah dan aplikasi
                // mengumumkan lagi. Sebelumnya cabang ini langsung return,
                // sehingga tombol "Panggil ulang" tidak mengirim notifikasi
                // maupun suara sama sekali.
                if ($status === self::STATUS_CALLED) {
                    $callTime = now();
                    $previousCall = $locked->called_at;

                    // `queue_event_id` memakai detik (`getTimestamp()`), dan
                    // kolom `called_at` menyimpan detik tanpa pecahan. Dua klik
                    // dalam detik yang sama akan menghasilkan id peristiwa yang
                    // identik — aplikasi menganggapnya duplikat dan panggilan
                    // kedua tidak diumumkan. Jadi waktu panggil selalu didorong
                    // minimal satu detik setelah panggilan sebelumnya.
                    if ($previousCall) {
                        $nextTick = $previousCall->copy()->addSecond();
                        if ($callTime->lessThan($nextTick)) {
                            $callTime = $nextTick;
                        }
                    }

                    $locked->forceFill(['called_at' => $callTime])->save();
                    $recalled = true;
                }

                return $locked;
            }

            if (!self::canTransition($previous, $status)) {
                throw new RuntimeException(
                    'Status antrian tidak bisa diubah dari '
                    . self::statusLabel($previous)
                    . ' ke ' . self::statusLabel($status) . '.'
                );
            }

            $payload = ['queue_status' => $status];

            if ($status === self::STATUS_CALLED) {
                $payload['called_at'] = now();
            }

            if ($status === self::STATUS_WAITING) {
                // Panggilan ulang / kembalikan ke daftar tunggu: waktu panggil
                // lama tidak boleh tampil sebagai panggilan aktif.
                $payload['called_at'] = null;
            }

            if (in_array($status, [self::STATUS_DONE, self::STATUS_NO_SHOW], true)) {
                $payload['queue_finished_at'] = now();
            }

            if ($status === self::STATUS_NO_SHOW) {
                $payload['queue_note'] = $note ? mb_substr(trim($note), 0, 255) : 'Pasien tidak merespons panggilan.';
            }

            $locked->forceFill($payload)->save();

            // `status` booking TIDAK diubah di sini. Antrian hanya mencatat
            // kehadiran fisik; booking ditandai `completed` saat dokter
            // menyimpan rekam medis. Dua sumbu ini sengaja dipisah supaya
            // laporan administrasi tidak berubah arti.

            return $locked;
        });

        if ($recalled || $previous !== $result->queue_status) {
            $result->loadMissing(['pet', 'doctor', 'clinic']);

            $event = match ($result->queue_status) {
                self::STATUS_CALLED => 'called',
                self::STATUS_IN_SERVICE => 'consultation_started',
                self::STATUS_DONE => 'consultation_completed',
                default => null,
            };

            if ($event) {
                $this->fcm->sendQueueEvent(
                    $result->user_id,
                    $event,
                    $result,
                    $result->status === 'completed' ? $result->medical_record_id : null
                );
            }
        }

        return $result;
    }

    /** Panggil antrian berikutnya (paling awal menunggu). */
    public function callNext(int $clinicId, ?string $date = null): ?Booking
    {
        $queueDate = $date ?: today()->toDateString();

        $next = Booking::where('clinic_id', $clinicId)
            ->whereDate('queue_date', $queueDate)
            ->where('queue_status', self::STATUS_WAITING)
            // Booking yang dibatalkan tidak boleh dipanggil lagi: pasiennya
            // sudah tidak datang, dan satu giliran terbuang sia-sia.
            ->where('status', '!=', 'cancelled')
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
            // Papan antrian hanya berisi pasien yang masih berlaku. Booking
            // batal tetap tersimpan di database (riwayat utuh) tetapi tidak
            // mengotori layar antrian maupun hitungan `total`.
            ->where('status', '!=', 'cancelled')
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
            // Nomor milik booking batal tidak lagi "di depan" siapa pun.
            ->where('status', '!=', 'cancelled')
            ->where('queue_number', '<', $booking->queue_number)
            ->count();
    }

    /**
     * Versi banyak-baris dari {@see positionOf()}.
     *
     * Satu query per kombinasi klinik+tanggal, bukan satu query per booking.
     * Halaman daftar booking berisi 20 baris; cara lama berarti 20 query hitung
     * tambahan setiap kali pengguna membuka tab Riwayat.
     *
     * @param  iterable<Booking>  $bookings
     * @return array<int, int|null>  booking_id => jumlah antrian di depannya
     */
    public function positionsFor(iterable $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if (!$booking->queue_number || !$booking->queue_date) {
                continue;
            }

            if (!in_array($booking->queue_status, self::ACTIVE_STATUSES, true)) {
                continue;
            }

            $key = $booking->clinic_id . '|' . substr((string) $booking->queue_date, 0, 10);
            $groups[$key]['clinic_id'] = $booking->clinic_id;
            $groups[$key]['date'] = substr((string) $booking->queue_date, 0, 10);
            $groups[$key]['bookings'][] = $booking;
        }

        $positions = [];

        foreach ($groups as $group) {
            $numbers = Booking::where('clinic_id', $group['clinic_id'])
                ->whereDate('queue_date', $group['date'])
                ->whereIn('queue_status', [self::STATUS_WAITING, self::STATUS_CALLED])
                ->where('status', '!=', 'cancelled')
                ->pluck('queue_number')
                ->map(fn ($number) => (int) $number)
                ->sort()
                ->values()
                ->all();

            foreach ($group['bookings'] as $booking) {
                $ahead = 0;
                foreach ($numbers as $number) {
                    if ($number >= (int) $booking->queue_number) {
                        break;
                    }
                    $ahead++;
                }

                $positions[$booking->id] = $ahead;
            }
        }

        return $positions;
    }
}
