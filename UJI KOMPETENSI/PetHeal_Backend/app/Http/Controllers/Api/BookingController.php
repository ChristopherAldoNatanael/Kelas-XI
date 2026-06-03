<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\FCMService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Get all bookings for authenticated user
     */
    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()
            ->with(['pet', 'doctor'])
            ->orderBy('booking_date', 'desc')
            ->orderBy('booking_time', 'desc')
            ->paginate(20);

        // Add full URL for doctor photos
        $bookings->getCollection()->transform(function ($booking) {
            if ($booking->doctor) {
                $booking->doctor->photo_url = $booking->doctor->photo
                    ? asset('storage/' . $booking->doctor->photo)
                    : null;
            }
            return $booking;
        });

        return response()->json([
            'success' => true,
            'data' => $bookings->items(),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Get upcoming bookings
     * ✅ OPTIMIZED: Limit to 1 result for Home Screen performance
     */
    public function upcoming(Request $request)
    {
        $bookings = $request->user()->bookings()
            ->upcoming()
            ->with(['pet', 'doctor'])
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(1) // Only fetch the very next booking for Home Screen
            ->get();

        // Add full URL for doctor photos
        $bookings->transform(function ($booking) {
            if ($booking->doctor) {
                $booking->doctor->photo_url = $booking->doctor->photo
                    ? asset('storage/' . $booking->doctor->photo)
                    : null;
            }
            return $booking;
        });

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }

    /**
     * Get single booking details
     */
    public function show(Request $request, $id)
    {
        $booking = $request->user()->bookings()
            ->with(['pet', 'doctor', 'medicalRecord'])
            ->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        // Add full URL for doctor photo
        if ($booking->doctor) {
            $booking->doctor->photo_url = $booking->doctor->photo
                ? asset('storage/' . $booking->doctor->photo)
                : null;
        }

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    /**
     * Create new booking
     */
    public function store(Request $request)
    {
        $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'doctor_id' => 'required|exists:doctors,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string|max:255',
            'payment_type' => 'nullable|in:dp,full',
            'total_amount' => 'nullable|numeric|min:0',
            'dp_amount' => 'nullable|numeric|min:0',
        ]);

        // Verify pet belongs to user
        $pet = $request->user()->pets()->find($request->input('pet_id'));
        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        // Check if slot is available
        $existingBooking = Booking::where('doctor_id', $request->input('doctor_id'))
            ->where('booking_date', $request->input('booking_date'))
            ->where('booking_time', $request->input('booking_time'))
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existingBooking) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked',
            ], 409);
        }

        // Calculate payment
        $paymentType = $request->input('payment_type', 'full');
        $totalAmount = $request->input('total_amount', config('services.booking.default_price'));
        $dpAmount = $request->input('dp_amount', 0);

        // If DP payment type, calculate DP as 50% if not provided
        if ($paymentType === 'dp') {
            $dpAmount = $dpAmount > 0 ? $dpAmount : ($totalAmount * 0.5);
            $paidAmount = 0; // Nothing paid yet - payment will be processed via Midtrans
            $remainingAmount = $dpAmount; // First payment is the DP amount
            $paymentStatus = 'dp_pending'; // Waiting for DP payment
        } else {
            // Full payment
            $paidAmount = 0; // Nothing paid yet - payment will be processed via Midtrans
            $remainingAmount = 0;
            $paymentStatus = 'pending'; // Waiting for full payment
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'pet_id' => $request->input('pet_id'),
            'doctor_id' => $request->input('doctor_id'),
            'booking_date' => $request->input('booking_date'),
            'booking_time' => $request->input('booking_time'),
            'status' => 'pending',
            'notes' => $request->input('notes'),
            'payment_method' => $request->input('payment_method'),
            'payment_type' => $paymentType,
            'total_amount' => $totalAmount,
            'dp_amount' => $dpAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $paymentStatus,
        ]);

        $booking->load(['pet', 'doctor']);

        // Add full URL for doctor photo
        if ($booking->doctor) {
            $booking->doctor->photo_url = $booking->doctor->photo
                ? asset('storage/' . $booking->doctor->photo)
                : null;
        }

        // Send notification (booking_date is now a plain string via accessor)
        $this->fcmService->sendBookingStatusUpdate(
            $request->user()->id,
            $pet->name,
            'pending',
            $booking->booking_date
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => $booking,
        ], 201);
    }

    /**
     * Cancel booking
     */
    public function cancel(Request $request, $id)
    {
        $booking = $request->user()->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found or cannot be cancelled',
            ], 404);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason', ''),
        ]);

        // Send notification
        $this->fcmService->sendBookingStatusUpdate(
            $request->user()->id,
            $booking->pet->name,
            'cancelled',
            $booking->booking_date
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled successfully',
            'data' => $booking,
        ]);
    }

    /**
     * Reschedule booking
     */
    public function reschedule(Request $request, $id)
    {
        $booking = $request->user()->bookings()
            ->where('status', 'pending')
            ->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found or cannot be rescheduled',
            ], 404);
        }

        $request->validate([
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
        ]);

        // Check if new slot is available
        $existingBooking = Booking::where('doctor_id', $booking->doctor_id)
            ->where('booking_date', $request->input('booking_date'))
            ->where('booking_time', $request->input('booking_time'))
            ->where('id', '!=', $id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if ($existingBooking) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked',
            ], 409);
        }

        $booking->update([
            'booking_date' => $request->input('booking_date'),
            'booking_time' => $request->input('booking_time'),
        ]);

        $booking->load(['pet', 'doctor']);

        // Add full URL for doctor photo
        if ($booking->doctor) {
            $booking->doctor->photo_url = $booking->doctor->photo
                ? asset('storage/' . $booking->doctor->photo)
                : null;
        }

        // Send notification
        $this->fcmService->sendBookingStatusUpdate(
            $request->user()->id,
            $booking->pet->name,
            'rescheduled',
            $booking->booking_date
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking rescheduled successfully',
            'data' => $booking,
        ]);
    }
}
