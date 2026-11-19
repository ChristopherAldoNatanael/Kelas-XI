<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\QueueService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Papan antrian harian klinik.
 *
 * Fokus operasional: siapa yang sedang menunggu, siapa yang sedang
 * ditangani, dan apa yang sudah selesai. Nomor antrian hanya diberikan saat
 * pasien benar-benar datang — tidak ada nomor "pesanan" yang dipesan jauh
 * hari, karena itu membuat nomor tidak berarti apa-apa di ruang tunggu.
 */
class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queue)
    {
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $clinicId = currentClinicId();

        // Super admin tanpa klinik tidak punya antrian untuk ditampilkan;
        // arahkan memilih klinik dulu alih-alih menampilkan data campur.
        if (!$clinicId) {
            return view('admin.queue.select_clinic', [
                'clinics' => \App\Models\Clinic::where('is_active', true)->orderBy('name')->get(),
            ]);
        }

        // Tanggal dari query string tidak boleh membuat halaman 500
        // (`Carbon::parse('abc')` melempar exception). Nilai tidak valid
        // diabaikan dan papan kembali ke hari ini.
        $date = $this->resolveDate($request);

        $board = $this->queue->board($clinicId, $date);

        // Filter dokter bersifat tampilan: nomor antrian tetap milik klinik.
        $doctorId = (int) $request->input('doctor_id', 0) ?: null;
        if ($doctorId) {
            $filter = fn ($collection) => $collection
                ->where('doctor_id', $doctorId)
                ->values();

            $board['waiting'] = $filter($board['waiting']);
            $board['serving'] = $filter($board['serving']);
            $board['finished'] = $filter($board['finished']);
            $board['stats'] = [
                'total' => $board['waiting']->count() + $board['serving']->count() + $board['finished']->count(),
                'waiting' => $board['waiting']->count(),
                'serving' => $board['serving']->count(),
                'done' => $board['finished']->where('queue_status', \App\Services\QueueService::STATUS_DONE)->count(),
                'no_show' => $board['finished']->where('queue_status', \App\Services\QueueService::STATUS_NO_SHOW)->count(),
            ];
        }

        // Booking yang belum check-in PADA TANGGAL PAPAN — ditampilkan supaya
        // admin bisa check-in langsung dari papan, bukan mencari di menu lain.
        //
        // Syaratnya bukan sekadar `queue_number IS NULL`: nomor bisa berasal
        // dari tanggal lain (mis. pasien terlanjur di-check-in untuk tanggal
        // yang salah). Booking seperti itu tidak muncul di papan tanggal ini
        // karena filternya `queue_date`, dan juga tidak muncul di sini kalau
        // hanya memeriksa `queue_number`. Akibatnya baris tidak bisa
        // di-check-in maupun dipanggil di tanggal booking sebenarnya.
        $notCheckedIn = Booking::with(['pet', 'doctor', 'user'])
            ->where('clinic_id', $clinicId)
            ->whereDate('booking_date', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('queue_number')
                    ->orWhereDate('queue_date', '!=', $date);
            })
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_time')
            ->get();

        $doctors = \App\Models\Doctor::where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Riwayat perubahan status antrian hari itu — diambil dari audit log
        // yang sudah ditulis controller, bukan tabel duplikat.
        $history = \App\Models\AuditLog::with('user')
            ->where('clinic_id', $clinicId)
            ->whereIn('action', ['queue.check_in', 'queue.check_in.patient', 'queue.status', 'queue.call_next', 'doctor.queue.called', 'doctor.queue.in_service', 'doctor.queue.no_show'])
            ->whereDate('created_at', $date)
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.queue.index', [
            'date' => $date,
            'isToday' => $date === today()->toDateString(),
            'board' => $board,
            'notCheckedIn' => $notCheckedIn,
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'history' => $history,
        ]);
    }

    /** Check-in: beri nomor antrian untuk tanggal papan yang sedang dibuka. */
    public function checkIn(Request $request, int $id)
    {
        $booking = $this->findScoped($id);

        // Tanggal papan WAJIB dipakai, bukan selalu `today()`. Kalau staf
        // membuka papan tanggal lain (mis. besok) lalu menekan Check-in,
        // nomor harus tetap berada di papan itu. Sebelumnya nomor selalu
        // dicap hari ini sehingga baris "hilang" dari papan yang sedang
        // dilihat dan tidak bisa dipanggil di tanggal tersebut.
        $date = $this->resolveDate($request);

        try {
            $updated = $this->queue->checkIn($booking, $date);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'queue.check_in',
            "Checked in booking #{$updated->id} — queue number {$updated->queue_number} ({$date})",
            $updated
        );

        return back()->with('success', "Nomor antrian {$updated->queue_number} diberikan untuk {$date}.");
    }

    /** Ubah status antrian (dipanggil / sedang ditangani / selesai / tidak datang). */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'queue_status' => 'required|in:' . implode(',', QueueService::STATUSES),
            'reason' => 'nullable|string|max:255',
        ]);

        $booking = $this->findScoped($id);

        try {
            $updated = $this->queue->transition(
                $booking,
                (string) $request->input('queue_status'),
                $request->input('reason')
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'queue.status',
            "Queue #{$updated->queue_number} → {$updated->queue_status}",
            $updated
        );

        return back()->with('success', 'Status antrian diperbarui.');
    }

    /** Panggil antrian berikutnya. */
    public function callNext(Request $request)
    {
        $clinicId = currentClinicId();
        if (!$clinicId) {
            return back()->with('error', 'Pilih klinik terlebih dahulu.');
        }

        $date = $this->resolveDate($request);

        $next = $this->queue->callNext($clinicId, $date);

        if (!$next) {
            return back()->with('warning', 'Tidak ada antrian yang menunggu.');
        }

        AuditLog::log('queue.call_next', "Called queue #{$next->queue_number}", $next);

        return back()->with('success', "Memanggil nomor antrian {$next->queue_number}.");
    }

    /**
     * Tanggal papan antrian. Hanya format `Y-m-d` yang diterima; nilai lain
     * jatuh ke hari ini supaya query string rusak tidak menghasilkan 500.
     */
    private function resolveDate(Request $request): string
    {
        $raw = (string) $request->input('date', '');

        return \Illuminate\Support\Carbon::hasFormat($raw, 'Y-m-d')
            ? $raw
            : today()->toDateString();
    }

    private function findScoped(int $id): Booking
    {
        $clinicId = currentClinicId();

        return Booking::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->whereKey($id)
            ->firstOrFail();
    }
}
