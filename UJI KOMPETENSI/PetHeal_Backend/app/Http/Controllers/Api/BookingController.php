<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        // PHASE 2 (contract freeze) + PHASE 1 (D3): tenant-aware `exists`
        // validation. A plain `exists:doctors,id` leaks cross-tenant IDs
        // (existence oracle) and defers the clinic check. Scoped rules make
        // Clinic A -> ID Clinic B fail validation. super_admin without clinic
        // keeps unscoped rules (existing behavior).
        $user = $request->user();
        $userClinicId = $user->clinic_id;
        $isSuperAdmin = ($user->role ?? null) === 'super_admin';

        // Fail closed before validating (middleware already blocks these,
        // this is defense in depth with the same 403 contract as Phase 1).
        if (!$userClinicId && !$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Akun Anda tidak terikat pada klinik manapun.',
            ], 403);
        }

        $doctorRule = Rule::exists('doctors', 'id')->where('is_active', true);
        $serviceRule = Rule::exists('services', 'id')->where('is_active', true);
        $petRule = Rule::exists('pets', 'id')->where('user_id', $user->id);
        if ($userClinicId) {
            $doctorRule->where('clinic_id', $userClinicId);
            $serviceRule->where('clinic_id', $userClinicId);
        }

        $request->validate([
            'pet_id' => ['required', $petRule],
            'doctor_id' => ['required', $doctorRule],
            'service_id' => ['required', $serviceRule],
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
            'payment_type' => 'nullable|in:dp,full',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
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

        // Clinic isolation (defense in depth: validation above is already
        // tenant-scoped). super_admin without clinic keeps existing behavior
        // (follows the doctor's clinic, used for manual/back-office bookings).
        if (!$userClinicId && !$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Akun Anda tidak terikat pada klinik manapun.',
            ], 403);
        }
        if ($userClinicId && (int) $doctor->clinic_id !== (int) $userClinicId) {
            return response()->json([
                'success' => false,
                'message' => 'Dokter tidak berada di klinik Anda.',
            ], 422);
        }
        if ($userClinicId && (int) $service->clinic_id !== (int) $userClinicId) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak berada di klinik Anda.',
            ], 422);
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
            // PHASE 3 (B4): the full total is due until paid. Previously 0,
            // which hid unpaid full-price bookings from every "amount due"
            // surface (dashboard outstanding, payment attention, admin list).
            $remainingAmount = $totalAmount;
            $paymentStatus = 'pending'; // Waiting for full payment
        }

        // PHASE 1 (C4): `bookings` has NO `payment_method_id` column in any
        // migration (only `payment_method` string, see
        // 2026_03_02_000001_add_payment_to_bookings_table.php). The previous
        // `'payment_method_id' => ...` key was silently discarded by
        // mass-assignment, losing the user's choice. Resolve the id to the
        // existing string column instead — no schema change, no format change.
        $paymentMethodName = null;
        if ($request->filled('payment_method_id')) {
            $paymentMethodName = PaymentMethod::whereKey($request->input('payment_method_id'))
                ->value('name');
        }

        $booking = DB::transaction(function () use ($request, $paymentType, $totalAmount, $dpAmount, $paidAmount, $remainingAmount, $paymentStatus, $doctor, $paymentMethodName) {
            // Double-book check scoped within the same clinic
            $existingBooking = Booking::where('doctor_id', $request->input('doctor_id'))
                ->where('clinic_id', $doctor->clinic_id)
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
                'payment_method' => $paymentMethodName,
                'payment_type' => $paymentType,
                'total_amount' => $totalAmount,
                'dp_amount' => $dpAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
                'clinic_id' => $doctor->clinic_id,
            ]);
        });

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is already booked',
            ], 409);
        }

        $booking->load(['pet', 'doctor']);

        // Send notification (booking_date is now a plain string via accessor)
        $this->fcmService->sendBookingStatusUpdate(
            $request->user()->id,
            $pet->name,
            'pending',
            $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
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
            // PHASE 3 (B16): NULL instead of '' so reports/queries see one
            // representation of "no reason" (admin side requires a reason).
            'cancellation_reason' => $request->filled('reason') ? $request->input('reason') : null,
        ]);

        // Send notification
        $this->fcmService->sendBookingStatusUpdate(
            $request->user()->id,
            $booking->pet->name,
            'cancelled',
            $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
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
            // Double-book check scoped within the same clinic
            $existingBooking = Booking::where('doctor_id', $booking->doctor_id)
                ->where('clinic_id', $booking->clinic_id)
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

        // Send notification
        $this->fcmService->sendBookingStatusUpdate(
            $request->user()->id,
            $booking->pet->name,
            'rescheduled',
            $booking->booking_date,
            $booking->id,
            $booking->pet_id,
            $booking->doctor_id
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
