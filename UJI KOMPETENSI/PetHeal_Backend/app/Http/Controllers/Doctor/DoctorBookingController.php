<?php

namespace App\Http\Controllers\Doctor;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\FCMService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Daftar & aksi booking milik dokter yang sedang login.
 *
 * Admin tetap memegang kendali seluruh klinik; portal dokter hanya boleh
 * menyentuh booking dengan `doctor_id` miliknya. Setiap transisi status memakai
 * row lock dan tetap mengirim notifikasi ke pemilik hewan seperti versi admin.
 */
class DoctorBookingController extends DoctorPortalController
{
    public function __construct(private readonly FCMService $fcmService)
    {
    }

    public function index(Request $request)
    {
        $doctor = $this->doctor();

        $validated = $request->validate([
            'date' => 'nullable|date',
            'status' => 'nullable|in:pending,confirmed,completed,cancelled',
            'q' => 'nullable|string|max:100',
        ]);

        // Tanpa parameter `date` → tampilkan hari ini. `date=` kosong sengaja
        // berarti "semua tanggal" supaya riwayat tetap bisa ditelusuri.
        $date = $request->has('date')
            ? ($validated['date'] ?? null)
            : now()->toDateString();
        $status = $validated['status'] ?? null;
        $search = trim((string) ($validated['q'] ?? ''));

        $query = Booking::with(['pet', 'user', 'service'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->when($date, fn ($q) => $q->whereDate('booking_date', $date))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('pet', fn ($pet) => $pet->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('user', fn ($owner) => $owner->where('name', 'like', '%' . $search . '%'));
                });
            });

        $bookings = $date
            ? $query->orderBy('booking_time')->paginate(15)->withQueryString()
            : $query->orderByDesc('booking_date')->orderByDesc('booking_time')->paginate(15)->withQueryString();

        $summaryQuery = Booking::query()
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->when($date, fn ($q) => $q->whereDate('booking_date', $date));

        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
            'confirmed' => (clone $summaryQuery)->where('status', 'confirmed')->count(),
            'completed' => (clone $summaryQuery)->where('status', 'completed')->count(),
        ];

        return view('doctor.bookings.index', compact(
            'doctor',
            'bookings',
            'summary',
            'date',
            'status',
            'search'
        ));
    }

    public function show($id)
    {
        $doctor = $this->doctor();

        $booking = Booking::with(['pet', 'user', 'service', 'medicalRecord'])
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->findOrFail($id);

        return view('doctor.bookings.show', compact('doctor', 'booking'));
    }

    public function confirm($id)
    {
        $booking = $this->transition($id, ['pending'], [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $sent = $this->notify($booking, 'confirmed');
        AuditLog::log('doctor.booking.confirm', "Dokter mengonfirmasi booking #{$booking->id} untuk {$booking->pet->name}.", $booking);

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Booking dikonfirmasi dan notifikasi terkirim ke pemilik hewan.'
                : 'Booking dikonfirmasi, tetapi notifikasi push gagal dikirim.'
        );
    }

    public function complete($id)
    {
        $booking = $this->transition($id, ['pending', 'confirmed'], [
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $sent = $this->notify($booking, 'completed');
        AuditLog::log('doctor.booking.complete', "Dokter menyelesaikan booking #{$booking->id} untuk {$booking->pet->name}.", $booking);

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Booking ditandai selesai dan notifikasi terkirim.'
                : 'Booking ditandai selesai, tetapi notifikasi push gagal dikirim.'
        );
    }

    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi supaya pemilik hewan tahu alasannya.',
        ]);

        $booking = $this->transition($id, ['pending', 'confirmed'], [
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason'),
        ]);

        $sent = $this->notify($booking, 'cancelled');
        AuditLog::log('doctor.booking.cancel', "Dokter membatalkan booking #{$booking->id} untuk {$booking->pet->name}.", $booking);

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Booking dibatalkan dan pemilik hewan sudah diberi tahu.'
                : 'Booking dibatalkan, tetapi notifikasi push gagal dikirim.'
        );
    }

    /**
     * @param  array<int, string>  $allowedFrom
     * @param  array<string, mixed>  $attributes
     */
    private function transition($id, array $allowedFrom, array $attributes): Booking
    {
        $doctor = $this->doctor();

        return DB::transaction(function () use ($id, $allowedFrom, $attributes, $doctor) {
            $booking = Booking::with('pet')
                ->where('doctor_id', $doctor->id)
                ->where('clinic_id', $doctor->clinic_id)
                ->whereIn('status', $allowedFrom)
                ->lockForUpdate()
                ->findOrFail($id);

            $booking->update($attributes);

            return $booking;
        });
    }

    private function notify(Booking $booking, string $status): bool
    {
        return $this->fcmService->sendBookingStatusUpdate(
            $booking->user_id,
            $booking->pet->name,
            $status,
            (string) $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
        );
    }
}
