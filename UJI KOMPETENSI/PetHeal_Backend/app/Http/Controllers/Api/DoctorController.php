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
     * Get all active doctors (clinic-scoped)
     */
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $clinicId = auth()->user()?->clinic_id;
        $cacheVersion = Cache::get('active_doctors_version', 1);
        // PHASE 1 (D3): key must separate super_admin overview ('all') from
        // fail-closed tenant scope, otherwise cached rows leak across scopes.
        $scopeKey = $clinicId ?: (isSuperAdmin() ? 'all' : 'none');
        $cacheKey = 'active_doctors:v' . $cacheVersion . ':c' . $scopeKey . ':' . md5($limit . '|' . strtolower($search));

        $doctors = Cache::remember($cacheKey, 900, function () use ($limit, $search, $clinicId) {
            $query = Doctor::where('is_active', true)
                ->withCount('reviews')
                // PHASE 3 (MH-01): preload the aggregate consumed by the
                // average_rating accessor (kills the per-row AVG query).
                ->withAvg('reviews', 'rating');

            // Clinic isolation: filter by user's clinic.
            // PHASE 1 (D3, fail-closed): tenant without clinic matches nothing.
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            } elseif (!isSuperAdmin()) {
                $query->whereRaw('1 = 0');
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "{$search}%")
                        ->orWhere('specialization', 'like', "{$search}%");
                });
            }

            return $query->orderBy('name')
                ->limit($limit)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $doctors,
        ]);
    }

    /**
     * Get single doctor details (clinic-scoped)
     */
    public function show($id)
    {
        // PHASE 3 (MH-01): withAvg feeds the average_rating accessor.
        $query = Doctor::withCount('reviews')->withAvg('reviews', 'rating')->where('id', $id);

        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        $clinicId = auth()->user()?->clinic_id;
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif (!isSuperAdmin()) {
            $query->whereRaw('1 = 0');
        }

        $doctor = $query->first();

        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

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

        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        $clinicId = auth()->user()?->clinic_id;
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif (!isSuperAdmin()) {
            $query->whereRaw('1 = 0');
        }
        $doctor = $query->first();

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
        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        $clinicId = auth()->user()?->clinic_id;
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif (!isSuperAdmin()) {
            $query->whereRaw('1 = 0');
        }
        $doctor = $query->first();
        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        // PHASE 2 (contract freeze): tenant-aware `booking_id` validation.
        // The booking must be owned by the reviewer AND live in the doctor's
        // clinic, so Clinic A -> booking ID Clinic B fails validation instead
        // of leaking existence. super_admin keeps ownership check only.
        $bookingRule = \Illuminate\Validation\Rule::exists('bookings', 'id')
            ->where('user_id', auth()->id())
            ->where('doctor_id', $doctor->id)
            ->where('clinic_id', $doctor->clinic_id)
            ->where('status', 'completed');

        $request->validate([
            'booking_id' => ['required', $bookingRule],
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

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

        // PHASE 1 (D3): booking must belong to the same clinic as the doctor
        // (and to the reviewer's clinic when the reviewer is a tenant user).
        // Prevents reviewing another clinic's completed booking by id guessing.
        if ((int) $booking->clinic_id !== (int) $doctor->clinic_id) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found or not completed',
            ], 404);
        }
        if ($clinicId && (int) $booking->clinic_id !== (int) $clinicId) {
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

        // Daftar dokter (index) di-cache 15 menit beserta agregat ratingnya,
        // sedangkan endpoint reviews selalu live. Tanpa ini, list tetap
        // menampilkan angka lama (mis. 5.0/1) padahal detail sudah 4.5/2 —
        // dan refresh dari Android tidak akan pernah bisa memperbaikinya.
        // Pola sama seperti Admin\DoctorController::refreshDoctorApiCache.
        Cache::forever('active_doctors_version', now()->timestamp);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => $review,
        ], 201);
    }

    public function getReviews($id)
    {
        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        $clinicId = auth()->user()?->clinic_id;
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif (!isSuperAdmin()) {
            $query->whereRaw('1 = 0');
        }
        $doctor = $query->first();
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

        // PHASE 2 (contract freeze): expose the paginator that was previously
        // discarded. Additive top-level `pagination` in canonical shape;
        // existing `data.*` keys untouched (Android ignores unknown keys).
        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->items(),
                'average_rating' => $averageRating ? round($averageRating, 1) : 0,
                'total_reviews' => $totalReviews,
            ],
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
