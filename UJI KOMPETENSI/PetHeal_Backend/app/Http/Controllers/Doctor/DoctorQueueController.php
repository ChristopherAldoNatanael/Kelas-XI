<?php

namespace App\Http\Controllers\Doctor;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\QueueService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Antrian pasien milik dokter yang sedang login.
 *
 * Dokter hanya melihat booking dengan `doctor_id` dirinya pada kliniknya.
 * Pemeriksaan dinyatakan selesai lewat penyimpanan rekam medis (lihat
 * DoctorMedicalRecordController) — halaman ini tidak membuat rekam medis
 * duplikat dan tidak menutup booking tanpa dokumen klinis.
 */
class DoctorQueueController extends DoctorPortalController
{
    public function __construct(private readonly QueueService $queue)
    {
    }

    public function index(Request $request)
    {
        $doctor = $this->doctor();

        $raw = (string) $request->input('date', '');
        $date = \Illuminate\Support\Carbon::hasFormat($raw, 'Y-m-d')
            ? $raw
            : today()->toDateString();

        $bookings = Booking::with(['pet', 'user', 'service', 'medicalRecord'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('queue_date', $date)
            ->whereNotNull('queue_number')
            ->orderBy('queue_number')
            ->get();

        // Sama seperti papan admin: booking yang nomornya berasal dari tanggal
        // lain harus tetap terlihat sebagai "belum check-in" di tanggal
        // booking-nya, bukan hilang dari dua papan sekaligus.
        $notCheckedIn = Booking::with(['pet', 'user'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->whereDate('booking_date', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('queue_number')
                    ->orWhereDate('queue_date', '!=', $date);
            })
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('booking_time')
            ->get();

        $waiting = $bookings->whereIn('queue_status', [QueueService::STATUS_WAITING, QueueService::STATUS_CALLED])->values();
        $serving = $bookings->where('queue_status', QueueService::STATUS_IN_SERVICE)->values();
        $finished = $bookings->whereIn('queue_status', [QueueService::STATUS_DONE, QueueService::STATUS_NO_SHOW])
            ->sortByDesc('queue_finished_at')->values();

        $stats = [
            'waiting' => $waiting->count(),
            'serving' => $serving->count(),
            'done' => $bookings->where('queue_status', QueueService::STATUS_DONE)->count(),
            'no_show' => $bookings->where('queue_status', QueueService::STATUS_NO_SHOW)->count(),
        ];

        return view('doctor.queue.index', compact(
            'doctor', 'date', 'waiting', 'serving', 'finished', 'notCheckedIn', 'stats'
        ));
    }

    public function call($id)
    {
        return $this->act($id, QueueService::STATUS_CALLED, 'Pasien dipanggil.');
    }

    public function recall($id)
    {
        return $this->act($id, QueueService::STATUS_CALLED, 'Panggilan ulang dikirim.');
    }

    public function start($id)
    {
        return $this->act($id, QueueService::STATUS_IN_SERVICE, 'Pemeriksaan dimulai.');
    }

    public function skip(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        return $this->act(
            $id,
            QueueService::STATUS_NO_SHOW,
            'Pasien dilewati.',
            $request->input('reason')
        );
    }

    private function act($id, string $status, string $success, ?string $note = null)
    {
        $doctor = $this->doctor();

        $booking = Booking::where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        try {
            $updated = $this->queue->transition($booking, $status, $note);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'doctor.queue.' . $status,
            "Dokter mengubah antrian #{$updated->queue_number} → {$updated->queue_status}.",
            $updated
        );

        return back()->with('success', $success);
    }
}
