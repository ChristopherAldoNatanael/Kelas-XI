<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DoctorController extends Controller
{
    /**
     * Get all active doctors
     */
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $cacheVersion = Cache::get('active_doctors_version', 1);
        $cacheKey = 'active_doctors:v' . $cacheVersion . ':' . md5($limit . '|' . strtolower($search));

        $doctors = Cache::remember($cacheKey, 900, function () use ($limit, $search) {
            $query = Doctor::where('is_active', true)
                ->withCount('reviews');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "{$search}%")
                        ->orWhere('specialization', 'like', "{$search}%");
                });
            }

            $doctors = $query->orderBy('name')
                ->limit($limit)
                ->get();

            $doctors->transform(function ($doctor) {
                $doctor->photo_url = $doctor->photo ? asset('storage/' . $doctor->photo) : null;
                return $doctor;
            });

            return $doctors;
        });

        return response()->json([
            'success' => true,
            'data' => $doctors,
        ]);
    }

    /**
     * Get single doctor details
     */
    public function show($id)
    {
        $doctor = Doctor::withCount('reviews')->find($id);

        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        // Add full URL for photo
        $doctor->photo_url = $doctor->photo ? asset('storage/' . $doctor->photo) : null;

        return response()->json([
            'success' => true,
            'data' => $doctor,
        ]);
    }

    /**
     * Get available time slots for a doctor on a specific date
     */
    public function getAvailableSlots(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $doctor = Doctor::find($id);

        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        $date = $request->input('date');
        $dayName = strtolower(date('l', strtotime($date)));

        // Check if doctor works on this day
        $availableDays = is_array($doctor->available_days)
            ? $doctor->available_days
            : array_map('trim', explode(',', $doctor->available_days ?? ''));

        if (!in_array($dayName, $availableDays)) {
            return response()->json([
                'success' => true,
                'data'    => [],   // empty flat array — no slots on this day
            ]);
        }

        // Generate time slots (flat array of {time, available})
        $slots = $this->generateTimeSlots($doctor, $date);

        return response()->json([
            'success' => true,
            'data'    => $slots,
        ]);
    }

    /**
     * Generate available time slots
     */
    private function generateTimeSlots(Doctor $doctor, string $date): array
    {
        $slots = [];
        /** @var string $startTime */
        $startTime = strtotime($doctor->start_time);
        /** @var string $endTime */
        $endTime = strtotime($doctor->end_time);
        $interval = 30 * 60; // 30 minutes

        // Get booked slots
        $bookedSlots = \App\Models\Booking::where('doctor_id', $doctor->id)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('booking_time')
            ->map(function ($time) {
                return date('H:i', strtotime($time));
            })
            ->toArray();

        for ($time = $startTime; $time < $endTime; $time += $interval) {
            $slotTime = date('H:i', $time);
            $slots[] = [
                'time' => $slotTime,
                'available' => !in_array($slotTime, $bookedSlots),
            ];
        }

        return $slots;
    }

    public function storeReview(Request $request, $id)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        $doctor = Doctor::find($id);
        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        $booking = \App\Models\Booking::where('id', $request->booking_id)
            ->where('user_id', auth()->id())
            ->where('doctor_id', $id)
            ->where('status', 'completed')
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found or not completed',
            ], 404);
        }

        $existing = DoctorReview::where('booking_id', $request->booking_id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this booking',
            ], 409);
        }

        $review = DoctorReview::create([
            'doctor_id' => $id,
            'user_id' => auth()->id(),
            'booking_id' => $request->booking_id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => $review,
        ], 201);
    }

    public function getReviews($id)
    {
        $doctor = Doctor::find($id);
        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        $reviews = DoctorReview::with('user:id,name')
            ->where('doctor_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $averageRating = DoctorReview::where('doctor_id', $id)->avg('rating');
        $totalReviews = DoctorReview::where('doctor_id', $id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->items(),
                'average_rating' => $averageRating ? round($averageRating, 1) : 0,
                'total_reviews' => $totalReviews,
            ],
        ]);
    }
}
