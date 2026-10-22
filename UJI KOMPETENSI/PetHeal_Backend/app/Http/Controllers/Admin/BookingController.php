<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorScheduleException;
use App\Services\DoctorScheduleService;
use App\Services\FCMService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookingController extends Controller
{
    protected FCMService $fcmService;

    public function __construct(
        FCMService $fcmService,
        private readonly DoctorScheduleService $schedule
    ) {
        $this->fcmService = $fcmService;
    }

    /**
     * List all bookings — clinic scoped
     */
    public function index(Request $request)
    {
        $clinicId = currentClinicId();

        $query = Booking::with(['user', 'pet', 'doctor', 'service'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId));

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('booking_date', $request->input('date'));
        }

        $bookings = $query->orderBy('booking_date', 'desc')
            ->orderBy('booking_time', 'desc')
            ->paginate(20);

        return view('admin.bookings.index', compact('bookings'));
    }

    /**
     * Daily schedule board — clinic scoped, grouped per doctor and time.
     *
     * The board intentionally reuses the existing booking status actions
     * (confirm/complete/cancel) instead of inventing a second workflow.
     */
    public function schedule(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $selectedDate = $validated['date'] ?? now()->toDateString();
        $clinicId = currentClinicId();

        // The schedule board is an operational, clinic-scoped screen. A super
        // admin in system-overview mode must choose a clinic first.
        if (!$clinicId) {
            $clinics = Clinic::where('is_active', true)->orderBy('name')->get();
            return view('admin.schedule.select_clinic', compact('clinics'));
        }

        $doctors = Doctor::query()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when(!empty($validated['doctor_id']), fn ($q) => $q->whereKey($validated['doctor_id']))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $bookings = Booking::with(['user', 'pet', 'doctor', 'service'])
            ->whereDate('booking_date', $selectedDate)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when(!empty($validated['doctor_id']), fn ($q) => $q->where('doctor_id', $validated['doctor_id']))
            ->orderBy('booking_time')
            ->get();

        $bookingsByDoctor = $bookings->groupBy('doctor_id');
        $statusCounts = $bookings->countBy('status');

        $stats = [
            'total' => $bookings->count(),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'confirmed' => (int) ($statusCounts['confirmed'] ?? 0),
            'completed' => (int) ($statusCounts['completed'] ?? 0),
            'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
        ];

        // PHASE 4: jadwal efektif per dokter untuk tanggal yang dipilih.
        // Papan jadwal memakai service yang sama dengan endpoint slot, jadi
        // badge "cuti" dan slot picker Android tidak mungkin berbeda.
        $dayExceptions = DoctorScheduleException::query()
            ->where('clinic_id', $clinicId)
            ->whereDate('date', $selectedDate)
            ->get()
            ->keyBy('doctor_id');

        $effectiveSchedules = [];
        $bookingsNeedingReview = [];
        foreach ($doctors as $doctor) {
            $schedule = $this->schedule->effectiveSchedule($doctor, $selectedDate);
            $effectiveSchedules[$doctor->id] = $schedule;

            foreach ($bookingsByDoctor[$doctor->id] ?? [] as $booking) {
                if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
                    continue;
                }
                if (!$this->schedule->isTimeWithinSchedule($schedule, (string) $booking->booking_time)) {
                    $bookingsNeedingReview[$booking->id] = true;
                }
            }
        }

        $selectedDoctorId = $validated['doctor_id'] ?? null;
        $previousDate = \Carbon\Carbon::parse($selectedDate)->subDay()->toDateString();
        $nextDate = \Carbon\Carbon::parse($selectedDate)->addDay()->toDateString();
        $isToday = \Carbon\Carbon::parse($selectedDate)->isToday();

        return view('admin.schedule.index', compact(
            'doctors',
            'bookingsByDoctor',
            'stats',
            'selectedDate',
            'selectedDoctorId',
            'previousDate',
            'nextDate',
            'isToday',
            'dayExceptions',
            'effectiveSchedules',
            'bookingsNeedingReview'
        ));
    }

    /**
     * Show booking details — clinic scoped
     */
    public function show($id)
    {
        $clinicId = currentClinicId();
        $query = Booking::with(['user', 'pet', 'doctor', 'service', 'medicalRecord'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $booking = $query->firstOrFail();

        return view('admin.bookings.show', compact('booking'));
    }

    /**
     * Confirm booking — clinic scoped
     */
    public function confirm($id)
    {
        $clinicId = currentClinicId();

        // PHASE 3 (C-01): status transition under row lock so concurrent
        // confirm/cancel/complete cannot interleave (last-write-wins).
        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId) {
            $query = Booking::with(['pet'])->where('status', 'pending')->where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $booking = $query->lockForUpdate()->firstOrFail();

            $booking->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            return $booking;
        });

        $sent = $this->fcmService->sendBookingStatusUpdate(
            $booking->user_id,
            $booking->pet->name,
            'confirmed',
            (string) $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
        );

        AuditLog::log('booking.confirm', "Confirmed booking #{$id} for {$booking->pet->name}", $booking);

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent ? 'Booking confirmed and notification sent successfully' : 'Booking confirmed, but push notification could not be delivered.'
        );
    }

    /**
     * Complete booking — clinic scoped
     */
    public function complete($id)
    {
        $clinicId = currentClinicId();

        // PHASE 3 (C-01): see confirm().
        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId) {
            $query = Booking::with(['pet'])->whereIn('status', ['pending', 'confirmed'])->where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $booking = $query->lockForUpdate()->firstOrFail();

            $booking->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $booking;
        });

        $sent = $this->fcmService->sendBookingStatusUpdate(
            $booking->user_id,
            $booking->pet->name,
            'completed',
            (string) $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
        );

        AuditLog::log('booking.complete', "Completed booking #{$id} for {$booking->pet->name}", $booking);

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent ? 'Booking marked as completed and notification sent' : 'Booking marked as completed, but push notification could not be delivered.'
        );
    }

    /**
     * Cancel booking — clinic scoped
     */
    public function cancel(Request $request, $id)
    {
        $clinicId = currentClinicId();

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // PHASE 3 (C-01): see confirm().
        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId, $request) {
            $query = Booking::with(['pet'])->whereIn('status', ['pending', 'confirmed'])->where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $booking = $query->lockForUpdate()->firstOrFail();

            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->input('reason'),
            ]);

            return $booking;
        });

        $sent = $this->fcmService->sendBookingStatusUpdate(
            $booking->user_id,
            $booking->pet->name,
            'cancelled',
            (string) $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
        );

        AuditLog::log('booking.cancel', "Cancelled booking #{$id} for {$booking->pet->name}", $booking);

        return redirect()->back()->with(
            $sent ? 'success' : 'warning',
            $sent ? 'Booking cancelled and notification sent successfully' : 'Booking cancelled, but push notification could not be delivered.'
        );
    }

    /**
     * Export bookings as PDF — clinic scoped
     */
    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            // PHASE 4: honor the index filters the UI hint promises.
            'status' => 'nullable|in:pending,confirmed,completed,cancelled',
            'date' => 'nullable|date',
        ]);

        $clinicId = currentClinicId();
        $query = Booking::with(['pet.user', 'doctor', 'service'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['date'])) {
            $query->whereDate('booking_date', $validated['date']);
        }

        if (!empty($validated['from'])) {
            $query->whereDate('booking_date', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->whereDate('booking_date', '<=', $validated['to']);
        }

        $bookings = $query->get();
        $html = view('admin.exports.bookings_pdf', compact('bookings'))->render();
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="bookings-export-' . now()->format('Y-m-d') . '.pdf"');
    }

    public function sendReminder(Request $request, $id)
    {
        $request->validate([
            'reminder_type'  => 'required|in:1_hour,tomorrow,custom',
            'custom_message' => 'nullable|string|max:255|required_if:reminder_type,custom',
        ]);

        $clinicId = currentClinicId();
        $query = Booking::with(['user', 'pet', 'doctor'])->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $booking = $query->firstOrFail();

        $sent = $this->fcmService->sendManualReminder(
            $booking->user_id,
            $booking->pet->name,
            $booking->doctor->name,
            (string) $booking->booking_date,
            (string) $booking->booking_time,
            (string) $request->input('reminder_type', 'tomorrow'),
            $request->input('custom_message'),
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
        );

        // PHASE 4: status reminder hanya dicatat saat benar-benar terkirim.
        // Pengiriman gagal tidak boleh membuat admin mengira pasien sudah
        // diingatkan (dan tidak boleh memblokir percobaan berikutnya).
        if ($sent) {
            $booking->update(['last_reminder_at' => now()]);
        }

        AuditLog::log('booking.send_reminder', "Sent reminder for booking #{$id} to {$booking->user->name}", $booking);

        return redirect()->back()
            ->with(
                $sent ? 'success' : 'warning',
                $sent
                    ? 'Reminder sent to ' . $booking->user->name . '\'s device!'
                    : 'Reminder request was saved, but push notification could not be delivered to the device.'
            );
    }
}
