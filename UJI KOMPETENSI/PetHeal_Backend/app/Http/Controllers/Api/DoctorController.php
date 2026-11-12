<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorReview;
use App\Services\DoctorScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DoctorController extends Controller
{
    public function __construct(private readonly DoctorScheduleService $schedule) {}

    /**
     * Get all active doctors (clinic-scoped)
     */
    /**
     * MULTI-CLINIC DISCOVERY: scope klinik untuk katalog dokter.
     *
     * Pasien (`user`) memilih dokter per klinik yang sedang dilihat, jadi
     * `?clinic_slug=` boleh dipakai; tanpa slug → seluruh dokter aktif
     * (read-only lintas klinik). `users.clinic_id` lama pada akun pasien
     * diabaikan. Role staf tetap terkurung pada kliniknya (fail-closed).
     */
    private function catalogClinicId(Request $request, ?int $requestedClinicId): ?int
    {
        // `$request->user()` (guard Sanctum) — bukan auth()->user() global yang
        // bisa menunjuk guard lain.
        $user = $request->user();

        // MULTI-CLINIC (2026-11-09): `?clinic_id=` memfilter katalog ke satu
        // klinik, dipakai alur booking Android supaya dokter yang dipilih
        // pasti praktik di klinik tujuan booking. Hanya klinik aktif yang
        // valid; klinik nonaktif/nonexistent => 0 (ditolak sebagai 404).
        $forcedId = $request->input('clinic_id');
        if ($forcedId !== null && $forcedId !== '') {
            if (!ctype_digit((string) $forcedId)) {
                return 0;
            }
            $exists = \App\Models\Clinic::where('id', (int) $forcedId)
                ->where('is_active', true)
                ->exists();
            return $exists ? (int) $forcedId : 0;
        }

        if ($requestedClinicId) {
            return $requestedClinicId;
        }

        // MULTI-CLINIC (2026-11-11): pasien = akun global. `users.clinic_id`
        // lama pada akun pasien TIDAK boleh menyempitkan katalog — kalau
        // dipakai, Cari Dokter dan "Dokter terdekat" hanya berisi klinik lama
        // dan pasien tidak pernah melihat klinik lain. Staf tetap fail-closed.
        $role = $user?->role ?? 'guest';
        if ($role === 'user') {
            return null;
        }

        return $user?->clinic_id ? (int) $user->clinic_id : null;
    }

    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $slug = trim((string) $request->query('clinic_slug', ''));
        $forcedClinicId = $request->input('clinic_id');
        $requestedClinicId = null;
        if ($slug !== '' && ($forcedClinicId === null || $forcedClinicId === '')) {
            $requestedClinicId = (int) (\App\Models\Clinic::where('slug', $slug)
                ->where('is_active', true)->value('id') ?: 0) ?: null;
            if (!$requestedClinicId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clinic not found',
                ], 404);
            }
        }
        $clinicId = $this->catalogClinicId($request, $requestedClinicId);
        // clinic_id yang tidak dikenal / klinik nonaktif -> 404, bukan daftar
        // kosong yang tampak seperti "klinik ini tidak punya dokter".
        if ($clinicId === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Clinic not found',
            ], 404);
        }
        $isPatient = (auth()->user()?->role ?? null) === 'user';
        $cacheVersion = Cache::get('active_doctors_version', 1);
        // PHASE 1 (D3): key must separate super_admin overview ('all') from
        // fail-closed tenant scope, otherwise cached rows leak across scopes.
        // MULTI-CLINIC: pasien tanpa slug = katalog lintas klinik ('all').
        $scopeKey = $clinicId ?: (($isPatient || isSuperAdmin()) ? 'all' : 'none');
        $cacheKey = 'active_doctors:v' . $cacheVersion . ':c' . $scopeKey . ':' . md5($limit . '|' . strtolower($search));

        $doctors = Cache::remember($cacheKey, 900, function () use ($limit, $search, $clinicId, $isPatient) {
            $query = Doctor::where('is_active', true)
                ->withCount('reviews')
                // PHASE 3 (MH-01): preload the aggregate consumed by the
                // average_rating accessor (kills the per-row AVG query).
                ->withAvg('reviews', 'rating')
                // MULTI-CLINIC DISCOVERY: dokter klinik NONAKTIF tidak boleh
                // tampil di katalog lintas klinik (pasien bisa booking ke sana
                // dan langsung gagal).
                ->whereHas('clinic', fn ($q) => $q->where('is_active', true))
                // MULTI-CLINIC (2026-11-09): setiap dokter membawa klinik
                // ringkasnya. Tanpa ini pasien yang melihat katalog lintas
                // klinik tidak punya cara tahu dokter itu praktik di mana —
                // sumber kebingungan utama alur booking.
                ->with(['clinic:id,name,slug,address,primary_color,latitude,longitude']);

            // Clinic isolation: filter by user's clinic.
            // PHASE 1 (D3, fail-closed): tenant staff without clinic matches
            // nothing. Pasien global boleh melihat katalog semua klinik.
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            } elseif (!$isPatient && !isSuperAdmin()) {
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
    /**
     * Cakupan klinik untuk endpoint DOKTER SATU-SATUNYA (detail, slot,
     * review).
     *
     * MULTI-CLINIC (2026-11-11): pasien (`role=user`) adalah akun global —
     * `users.clinic_id` lama TIDAK boleh dipakai sebagai filter, karena
     * pasien lama akan mendapat 404 saat membuka dokter klinik lain dari
     * Cari Dokter / Beranda (docs §6l). Staf tetap terkurung ke kliniknya
     * (fail-closed bila staf tanpa klinik).
     *
     * @return array{0: ?int, 1: bool} [clinicId, isScopedStaff]
     */
    private function doctorScope(Request $request): array
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $isScopedStaff = (($user?->role ?? null) ?? 'guest') !== 'user';
        if ($isScopedStaff) {
            return [$user?->clinic_id ? (int) $user->clinic_id : null, true];
        }

        return [null, false];
    }

    public function show(Request $request, $id)
    {
        // PHASE 3 (MH-01): withAvg feeds the average_rating accessor.
        $query = Doctor::withCount('reviews')->withAvg('reviews', 'rating')->where('id', $id);

        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        // MULTI-CLINIC: pasien global (role `user`) boleh melihat detail dokter
        // klinik mana pun; staf klinik tetap terkurung.
        [$clinicId, $isScopedStaff] = $this->doctorScope($request);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif ($isScopedStaff && !isSuperAdmin()) {
            $query->whereRaw('1 = 0');
        }
        // MULTI-CLINIC (2026-11-09): `?clinic_id=` mempersempit ke satu klinik.
        // Pasien global sudah boleh lintas klinik; parameter ini untuk klien
        // yang ingin memastikan dokter berasal dari klinik tertentu. Staf
        // tetap terkunci ke kliniknya sendiri (parameter tidak melonggarkan).
        if (!$isScopedStaff) {
            $requestedClinic = $request->input('clinic_id');
            if ($requestedClinic !== null && $requestedClinic !== ''
                && ctype_digit((string) $requestedClinic)) {
                $query->where('clinic_id', (int) $requestedClinic);
            }
        }

        $doctor = $query->first();

        if (!$doctor || !$doctor->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        // PHASE 4: upcoming tanggal cuti/jam khusus ikut di detail dokter agar
        // date rail Android bisa menandai hari libur tanpa request tambahan.
        // Additive — klien lama mengabaikan key yang tidak dikenal.
        $doctor->load(['scheduleExceptions' => function ($query) {
            $query->whereDate('date', '>=', today())
                ->orderBy('date')
                ->limit(30);
        }]);
        // MULTI-CLINIC: klinik dokter ikut di detail agar layar booking bisa
        // memastikan user melihat klinik tujuan yang benar.
        $doctor->load(['clinic:id,name,slug,address,primary_color,latitude,longitude']);

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
        // Slot dokter dipakai pasien klinik mana pun saat booking.
        [$clinicId, $isScopedStaff] = $this->doctorScope($request);
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif ($isScopedStaff && !isSuperAdmin()) {
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

        // PHASE 4: jadwal efektif = pengecualian tanggal (cuti/jam khusus)
        // lebih dulu, baru jadwal mingguan. Satu sumber dengan validasi booking.
        $schedule = $this->schedule->effectiveSchedule($doctor, $date);
        $slots = $this->schedule->slotsFor($doctor, $date, $schedule);

        return response()->json([
            'success' => true,
            'data'    => $slots,
            // Additive metadata: klien lama tetap hanya membaca `data`.
            'meta'    => [
                'doctor_id' => $doctor->id,
                'date' => $date,
                'is_working_day' => $schedule['is_working_day'],
                'is_closed' => $schedule['is_closed'],
                'has_exception' => $schedule['has_exception'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'reason' => $schedule['reason'],
            ],
        ]);
    }

    public function storeReview(Request $request, $id)
    {
        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        // Slot dokter dipakai pasien klinik mana pun saat booking.
        [$clinicId, $isScopedStaff] = $this->doctorScope($request);
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif ($isScopedStaff && !isSuperAdmin()) {
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
        // MULTI-CLINIC: pasien global tidak punya klinik, jadi cukup booking
        // miliknya + klinik booking == klinik dokter.
        if ((int) $booking->clinic_id !== (int) $doctor->clinic_id) {
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

    public function getReviews(Request $request, $id)
    {
        // Clinic isolation (PHASE 1 D3, fail-closed for tenant without clinic).
        // Slot dokter dipakai pasien klinik mana pun saat booking.
        [$clinicId, $isScopedStaff] = $this->doctorScope($request);
        $query = Doctor::where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } elseif ($isScopedStaff && !isSuperAdmin()) {
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
                // MULTI-CLINIC: null = belum ada ulasan (BUKAN 0). Angka 0
                // berarti "dinilai 0/5" dan bertentangan dengan aturan
                // "jangan ganti NULL jadi 0" (MULTI_CLINIC §11).
                'average_rating' => $averageRating !== null
                    ? round((float) $averageRating, 1)
                    : null,
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
