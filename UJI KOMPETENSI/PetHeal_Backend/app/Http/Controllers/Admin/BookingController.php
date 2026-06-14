<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\FCMService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookingController extends Controller
{
    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * List all bookings
     */
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'pet', 'doctor', 'service']);

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
     * Show booking details
     */
    public function show($id)
    {
        $booking = Booking::with(['user', 'pet', 'doctor', 'service', 'medicalRecord'])->findOrFail($id);

        return view('admin.bookings.show', compact('booking'));
    }

    /**
     * Confirm booking
     */
    public function confirm($id)
    {
        $booking = Booking::with(['pet'])->where('status', 'pending')->findOrFail($id);

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

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
     * Complete booking
     */
    public function complete($id)
    {
        $booking = Booking::with(['pet'])->whereIn('status', ['pending', 'confirmed'])->findOrFail($id);

        $booking->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

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
     * Cancel booking
     */
    public function cancel(Request $request, $id)
    {
        $booking = Booking::with(['pet'])->whereIn('status', ['pending', 'confirmed'])->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $booking->update([
            'status'               => 'cancelled',
            'cancellation_reason'  => $request->input('reason'),
        ]);

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
     * Export bookings as PDF
     */
    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $query = Booking::with(['pet.user', 'doctor', 'service'])->latest();

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

        $booking = Booking::with(['user', 'pet', 'doctor'])->findOrFail($id);

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
