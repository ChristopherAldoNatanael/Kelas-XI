<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Jobs\SendFcmNotification;
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
        $query = Booking::with(['user', 'pet', 'doctor']);

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
        $booking = Booking::with(['user', 'pet', 'doctor', 'medicalRecord'])->findOrFail($id);

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

        // Send notification (queued for background processing)
        SendFcmNotification::dispatch(
            $booking->user_id,
            'Booking Confirmed',
            "Your booking for {$booking->pet->name} on {$booking->formatted_booking_date} has been confirmed",
            ['type' => 'booking_status', 'pet_name' => $booking->pet->name, 'status' => 'confirmed', 'date' => (string) $booking->booking_date]
        );

        AuditLog::log('booking.confirm', "Confirmed booking #{$id} for {$booking->pet->name}", $booking);

        return redirect()->back()->with('success', 'Booking confirmed successfully');
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

        SendFcmNotification::dispatch(
            $booking->user_id,
            'Booking Completed',
            "Your booking for {$booking->pet->name} on {$booking->formatted_booking_date} has been completed",
            ['type' => 'booking_status', 'pet_name' => $booking->pet->name, 'status' => 'completed', 'date' => (string) $booking->booking_date]
        );

        AuditLog::log('booking.complete', "Completed booking #{$id} for {$booking->pet->name}", $booking);

        return redirect()->back()->with('success', 'Booking marked as completed');
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

        SendFcmNotification::dispatch(
            $booking->user_id,
            'Booking Cancelled',
            "Your booking for {$booking->pet->name} on {$booking->formatted_booking_date} has been cancelled",
            ['type' => 'booking_status', 'pet_name' => $booking->pet->name, 'status' => 'cancelled', 'date' => (string) $booking->booking_date]
        );

        AuditLog::log('booking.cancel', "Cancelled booking #{$id} for {$booking->pet->name}", $booking);

        return redirect()->back()->with('success', 'Booking cancelled successfully');
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

        $bookings = $query->limit(200)->get();
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

        SendFcmNotification::dispatch(
            $booking->user_id,
            'Appointment Reminder',
            "Reminder: {$booking->pet->name}'s appointment with {$booking->doctor->name} is coming up!",
            ['type' => 'booking_reminder', 'pet_name' => $booking->pet->name, 'doctor' => $booking->doctor->name, 'date' => (string) $booking->booking_date, 'time' => $booking->booking_time]
        );

        AuditLog::log('booking.send_reminder', "Sent reminder for booking #{$id} to {$booking->user->name}", $booking);

        return redirect()->back()
            ->with('success', 'Reminder sent to ' . $booking->user->name . '\'s device!');
    }
}
