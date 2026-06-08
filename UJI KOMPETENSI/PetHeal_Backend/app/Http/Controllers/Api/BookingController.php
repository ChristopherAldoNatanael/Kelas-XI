<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Service;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'service_id' => 'required|exists:services,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
            'payment_type' => 'nullable|in:dp,full',
        ]);

        // Verify pet belongs to user
        $pet = $request->user()->pets()->find($request->input('pet_id'));
        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'Pet not found',
            ], 404);
        }

        $doctor = Doctor::where('is_active', true)->find($request->input('doctor_id'));
        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        $service = Service::active()->find($request->input('service_id'));
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found',
            ], 404);
        }

        // Calculate payment
        $paymentType = $request->input('payment_type', 'full');
        $totalAmount = (float) $service->price;
        $dpAmount = 0;

        // If DP payment type, calculate DP as 50% if not provided
        if ($paymentType === 'dp') {
            $dpAmount = $totalAmount * 0.5;
            $paidAmount = 0; // Nothing paid yet - payment will be processed via Midtrans
            $remainingAmount = $dpAmount; // First payment is the DP amount
            $paymentStatus = 'dp_pending'; // Waiting for DP payment
        } else {
            // Full payment
            $paidAmount = 0; // Nothing paid yet - payment will be processed via Midtrans
            $remainingAmount = 0;
            $paymentStatus = 'pending'; // Waiting for full payment
        }

        $booking = DB::transaction(function () use ($request, $paymentType, $totalAmount, $dpAmount, $paidAmount, $remainingAmount, $paymentStatus) {
            $existingBooking = Booking::where('doctor_id', $request->input('doctor_id'))
                ->where('booking_date', $request->input('booking_date'))
                ->where('booking_time', $request->input('booking_time'))
                ->whereIn('status', ['pending', 'confirmed'])
                ->lockForUpdate()
                ->first();

            if ($existingBooking) {
                return null;
            }

            return Booking::create([
                'user_id' => $request->user()->id,
                'pet_id' => $request->input('pet_id'),
                'doctor_id' => $request->input('doctor_id'),
                'service_id' => $request->input('service_id'),
                'booking_date' => $request->input('booking_date'),
                'booking_time' => $request->input('booking_time'),
                'status' => 'pending',
                'notes' => $request->input('notes'),
                'payment_type' => $paymentType,
                'total_amount' => $totalAmount,
                'dp_amount' => $dpAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
            ]);
        });

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked',
            ], 409);
        }

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

        $rescheduled = DB::transaction(function () use ($booking, $request, $id) {
            $existingBooking = Booking::where('doctor_id', $booking->doctor_id)
                ->where('booking_date', $request->input('booking_date'))
                ->where('booking_time', $request->input('booking_time'))
                ->where('id', '!=', $id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->lockForUpdate()
                ->first();

            if ($existingBooking) {
                return false;
            }

            $booking->update([
                'booking_date' => $request->input('booking_date'),
                'booking_time' => $request->input('booking_time'),
            ]);

            return true;
        });

        if (!$rescheduled) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked',
            ], 409);
        }

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

    public function destroy(Request $request, $id)
    {
        $booking = $request->user()->bookings()
            ->whereIn('status', ['pending', 'cancelled'])
            ->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found or cannot be deleted',
            ], 404);
        }

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => 'Booking deleted successfully',
        ]);
    }
}
