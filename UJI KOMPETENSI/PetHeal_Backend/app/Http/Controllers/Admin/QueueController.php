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

        // Booking hari ini yang belum check-in — ditampilkan supaya admin
        // bisa check-in langsung dari papan, bukan mencari di menu lain.
        $notCheckedIn = Booking::with(['pet', 'doctor', 'user'])
            ->where('clinic_id', $clinicId)
            ->whereDate('booking_date', $date)
            ->whereNull('queue_number')
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_time')
            ->get();

        return view('admin.queue.index', [
            'date' => $date,
            'isToday' => $date === today()->toDateString(),
            'board' => $board,
            'notCheckedIn' => $notCheckedIn,
        ]);
    }

    /** Check-in: beri nomor antrian. */
    public function checkIn(Request $request, int $id)
    {
        $booking = $this->findScoped($id);

        try {
            $updated = $this->queue->checkIn($booking);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'queue.check_in',
            "Checked in booking #{$updated->id} — queue number {$updated->queue_number}",
            $updated
        );

        return back()->with('success', "Nomor antrian {$updated->queue_number} diberikan.");
    }

    /** Ubah status antrian (dipanggil / sedang ditangani / selesai / tidak datang). */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'queue_status' => 'required|in:' . implode(',', QueueService::STATUSES),
        ]);

        $booking = $this->findScoped($id);

        try {
            $updated = $this->queue->transition($booking, (string) $request->input('queue_status'));
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
