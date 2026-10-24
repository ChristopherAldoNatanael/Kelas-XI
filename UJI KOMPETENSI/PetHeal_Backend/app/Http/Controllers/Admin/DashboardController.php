<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Admin dashboard — shows system overview for super_admin, clinic stats for clinic_admin
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Super admin without clinic selected → system overview
        if ($user && $user->role === 'super_admin' && !session('current_clinic_id')) {
            return $this->superAdminDashboard();
        }

        $clinicId = currentClinicId();

        // Date range filter — only applies when both inputs contain a valid
        // date. Empty inputs are treated as "no filter", and malformed values
        // are rejected before they reach Carbon/DB.
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $hasDateFilter = $request->filled('start_date') && $request->filled('end_date');
        $startDate = $hasDateFilter
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : null;
        $endDate = $hasDateFilter
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : null;

        // Today's statistics — clinic scoped
        $todayBookings = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))->today()->count();
        $pendingBookings = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))->pending()->count();
        $totalPatients = Pet::when($clinicId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('clinic_id', $clinicId)))->count();
        $totalDoctors = Doctor::where('is_active', true)->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))->count();

        // Cache key includes the clinic and a schema version. Bump the version
        // whenever the shared payload changes so old cached arrays cannot
        // reach the view missing keys.
        $cacheKey = 'dashboard_stats_v3_c' . ($clinicId ?: 'all') . '_all';

        $buildDashboardStats = function () use ($startDate, $endDate, $hasDateFilter, $clinicId) {
            // Revenue statistics — clinic scoped
            $todayRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->whereDate('created_at', today())
                ->value('total') ?? 0;

            $monthlyRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->value('total') ?? 0;

            // Honest comparisons. "MTD" is compared with the same number of
            // days in the previous month, not the full previous month, so the
            // badge does not lie early in the month.
            $previousMonthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
            $previousMonthDay = min(Carbon::now()->day, $previousMonthStart->daysInMonth);
            $previousMonthEnd = $previousMonthStart->copy()->addDays($previousMonthDay - 1)->endOfDay();
            $previousMonthRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
                ->value('total') ?? 0;

            $yesterdayRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->whereDate('created_at', Carbon::yesterday())
                ->value('total') ?? 0;

            $monthlyRevenueGrowth = $previousMonthRevenue > 0
                ? round((($monthlyRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100, 1)
                : null;
            $dailyRevenueGrowth = $yesterdayRevenue > 0
                ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
                : null;

            // Monthly chart data
            $monthlyBookingData = $this->getMonthlyBookingData($clinicId);
            $monthlyRevenueData = $this->getMonthlyRevenueData($clinicId);
            $dailyRevenueData = $this->getDailyRevenueData($clinicId);

            // Payment statistics — clinic scoped
            $baseBooking = fn() => Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]));

            $unpaidBookings = (clone $baseBooking())->where('payment_status', 'unpaid')->count();
            $pendingPayment = (clone $baseBooking())->where('payment_status', 'pending')->count();
            $dpPendingBookings = (clone $baseBooking())->where('payment_status', 'dp_pending')->count();
            $dpPaidBookings = (clone $baseBooking())->where('payment_status', 'dp_paid')->count();
            $paidInFull = (clone $baseBooking())->where('payment_status', 'paid')->count();
            $failedPayments = (clone $baseBooking())->where('payment_status', 'failed')->count();

            $totalCollected = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(paid_amount), 0) as total')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->value('total') ?? 0;
            $totalOutstanding = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(remaining_amount), 0) as total')
                ->where('remaining_amount', '>', 0)
                ->where('status', '!=', 'cancelled')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->value('total') ?? 0;

            $totalBilled = $totalCollected + $totalOutstanding;
            $collectionRate = $totalBilled > 0
                ? (int) round(($totalCollected / $totalBilled) * 100)
                : 0;

            return compact(
                'todayRevenue', 'monthlyRevenue',
                'monthlyRevenueGrowth', 'dailyRevenueGrowth',
                'monthlyBookingData', 'monthlyRevenueData', 'dailyRevenueData',
                'unpaidBookings', 'pendingPayment', 'dpPendingBookings', 'dpPaidBookings', 'paidInFull', 'failedPayments',
                'totalCollected', 'totalOutstanding', 'totalBilled', 'collectionRate'
            );
        };

        // Date-ranged dashboards are ad-hoc queries; caching them made the
        // data stale after a payment because the old key could not be
        // invalidated without enumerating every possible range. Compute those
        // requests live and keep the 5-minute cache only for the default view.
        $dashboardStats = $hasDateFilter
            ? $buildDashboardStats()
            : Cache::remember($cacheKey, 300, $buildDashboardStats);

        $popularDoctors = $this->getPopularDoctors($clinicId);

        $todayRevenue = $dashboardStats['todayRevenue'];
        $monthlyRevenue = $dashboardStats['monthlyRevenue'];
        $monthlyBookingData = $dashboardStats['monthlyBookingData'];
        $monthlyRevenueData = $dashboardStats['monthlyRevenueData'];
        $dailyRevenueData = $dashboardStats['dailyRevenueData'];
        $unpaidBookings = $dashboardStats['unpaidBookings'];
        $pendingPayment = $dashboardStats['pendingPayment'];
        $dpPendingBookings = $dashboardStats['dpPendingBookings'];
        $dpPaidBookings = $dashboardStats['dpPaidBookings'];
        $paidInFull = $dashboardStats['paidInFull'];
        $failedPayments = $dashboardStats['failedPayments'];
        $totalCollected = $dashboardStats['totalCollected'];
        $totalOutstanding = $dashboardStats['totalOutstanding'];
        $totalBilled = $dashboardStats['totalBilled'];
        $collectionRate = $dashboardStats['collectionRate'];
        $monthlyRevenueGrowth = $dashboardStats['monthlyRevenueGrowth'];
        $dailyRevenueGrowth = $dashboardStats['dailyRevenueGrowth'];

        // Recent bookings — clinic scoped
        $recentBookings = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'status', 'booking_date', 'booking_time', 'created_at')
            ->with(['user:id,name,email', 'pet:id,name,user_id', 'doctor:id,name,specialization'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent payments — clinic scoped
        $recentPayments = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'paid_amount', 'total_amount', 'payment_status', 'payment_type', 'remaining_amount', 'updated_at')
            ->where('paid_amount', '>', 0)
            ->with(['user:id,name', 'pet:id,name'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Bookings with outstanding balance — clinic scoped. The badge and
        // the list share the same criteria (non-cancelled, remaining > 0),
        // so the count is not silently capped at the five visible rows.
        $outstandingQuery = Booking::query()
            ->where('remaining_amount', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]));

        $outstandingCount = (clone $outstandingQuery)->count();
        $outstandingBookings = $outstandingQuery
            ->select('id', 'user_id', 'pet_id', 'remaining_amount', 'payment_type')
            ->with(['user:id,name', 'pet:id,name'])
            ->orderBy('remaining_amount', 'desc')
            ->limit(5)
            ->get();

        // Midtrans transaction count — clinic scoped.
        // PHASE 3 (B11): `payment_method` stores the selected method NAME
        // (QRIS/GoPay/...), never the literal 'midtrans', so the old counter
        // was ~always 0. Count bookings with Midtrans-processed events
        // instead (same Blade variable, corrected value).
        $clinicBookingIds = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->select('id');
        $midtransTransactions = \Illuminate\Support\Facades\DB::table('payment_events')
            ->whereIn('booking_id', $clinicBookingIds)
            ->distinct()
            ->count('booking_id');

        return view('admin.dashboard', compact(
            'todayBookings',
            'pendingBookings',
            'totalPatients',
            'totalDoctors',
            'todayRevenue',
            'monthlyRevenue',
            'monthlyBookingData',
            'monthlyRevenueData',
            'dailyRevenueData',
            'popularDoctors',
            'recentBookings',
            // Payment data
            'unpaidBookings',
            'pendingPayment',
            'dpPendingBookings',
            'dpPaidBookings',
            'paidInFull',
            'failedPayments',
            'totalCollected',
            'totalOutstanding',
            'totalBilled',
            'collectionRate',
            'monthlyRevenueGrowth',
            'dailyRevenueGrowth',
            'recentPayments',
            'outstandingBookings',
            'outstandingCount',
            'midtransTransactions'
        ));
    }

    /**
     * Get monthly bookings data for chart (last 6 months) — clinic scoped
     */
    private function getMonthlyBookingData(?int $clinicId): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $raw = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->where('booking_date', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(booking_date, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i)->locale(app()->getLocale());
            $key = $date->format('Y-m');
            $labels[] = $date->translatedFormat('M Y');
            $data[] = (int) ($raw[$key] ?? 0);
        }

        return compact('labels', 'data');
    }

    /**
     * Get monthly revenue data for chart (last 6 months) — clinic scoped
     */
    private function getMonthlyRevenueData(?int $clinicId): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $raw = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i)->locale(app()->getLocale());
            $key = $date->format('Y-m');
            $labels[] = $date->translatedFormat('M Y');
            $data[] = (float) ($raw[$key] ?? 0);
        }

        return compact('labels', 'data');
    }

    /**
     * Get daily revenue data for chart (last 7 days) — clinic scoped
     */
    private function getDailyRevenueData(?int $clinicId): array
    {
        $raw = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as day")
            ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->locale(app()->getLocale());
            $key = $date->format('Y-m-d');
            $labels[] = $date->translatedFormat('D');
            $data[] = (float) ($raw[$key] ?? 0);
        }

        return compact('labels', 'data');
    }

    /**
     * Get top 5 doctors by booking count — clinic scoped
     */
    private function getPopularDoctors(?int $clinicId): array
    {
        $doctors = Doctor::select('id', 'name')
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'labels' => $doctors->pluck('name')->toArray(),
            'data' => $doctors->pluck('bookings_count')->toArray(),
        ];
    }

    public function auditLogs()
    {
        $clinicId = currentClinicId();
        $logs = AuditLog::with('user')
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->latest()
            ->paginate(50);
        return view('admin.audit-logs', compact('logs'));
    }

    /**
     * System-wide dashboard for super_admin
     */
    private function superAdminDashboard()
    {
        // PHASE 5: constrain per-row counts to the same denominators as the
        // summary cards (active doctors/services, role=user) so rows and
        // cards cannot disagree.
        $clinics = Clinic::withCount([
            'doctors' => fn ($q) => $q->where('is_active', true),
            'services' => fn ($q) => $q->where('is_active', true),
            'bookings',
            'users' => fn ($q) => $q->where('role', 'user'),
        ])
            ->orderBy('name')
            ->get();

        $totalClinics = $clinics->count();
        $activeClinics = $clinics->where('is_active', true)->count();
        $totalDoctors = Doctor::where('is_active', true)->count();
        $totalServices = Service::where('is_active', true)->count();
        $totalBookings = Booking::count();
        $totalUsers = User::where('role', 'user')->count();
        $pendingJoinRequests = ClinicJoinRequest::where('status', 'pending')->count();
        $totalRevenue = MedicalRecord::selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')->value('total') ?? 0;

        return view('admin.super-dashboard', compact(
            'clinics',
            'totalClinics',
            'activeClinics',
            'totalDoctors',
            'totalServices',
            'totalBookings',
            'totalUsers',
            'pendingJoinRequests',
            'totalRevenue'
        ));
    }

    /**
     * Global command palette search: bookings, pets, and owners.
     *
     * Every query is clinic-scoped with currentClinicId(). A super admin
     * without a selected clinic keeps the existing system-overview scope.
     */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $clinicId = currentClinicId();

        if (mb_strlen($term) < 2) {
            return response()->json([
                'success' => true,
                'query' => $term,
                'data' => ['bookings' => [], 'pets' => [], 'owners' => []],
            ]);
        }

        // Command palette must never mix clinics. Super admin can search after
        // explicitly selecting a clinic from the switcher.
        if (!$clinicId) {
            return response()->json([
                'success' => true,
                'query' => $term,
                'message' => 'Pilih klinik terlebih dahulu untuk mencari data operasional.',
                'data' => ['bookings' => [], 'pets' => [], 'owners' => []],
            ]);
        }

        $like = '%' . $term . '%';

        $bookings = Booking::with(['pet:id,name', 'user:id,name', 'doctor:id,name'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where(function ($q) use ($term, $like) {
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
                $q->orWhereHas('pet', fn ($pq) => $pq->where('name', 'like', $like))
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $like));
            })
            ->orderByDesc('booking_date')
            ->orderByDesc('booking_time')
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'type' => 'booking',
                'title' => '#' . $booking->id . ' · ' . ($booking->pet->name ?? 'Booking'),
                'subtitle' => collect([
                    $booking->user->name ?? null,
                    $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') : null,
                    $booking->booking_time,
                    $booking->status,
                ])->filter()->join(' · '),
                'url' => route('admin.bookings.show', $booking->id),
            ])
            ->values();

        $pets = Pet::with('user:id,name,clinic_id')
            ->when($clinicId, fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('clinic_id', $clinicId)))
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('species', 'like', $like)
                    ->orWhere('breed', 'like', $like)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $like));
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Pet $pet) => [
                'id' => $pet->id,
                'type' => 'pet',
                'title' => $pet->name ?? 'Hewan',
                'subtitle' => collect([
                    $pet->species,
                    $pet->breed,
                    $pet->user->name ?? null,
                ])->filter()->join(' · '),
                'url' => $pet->user_id ? route('admin.users.show', $pet->user_id) : route('admin.users.index'),
            ])
            ->values();

        $owners = User::query()
            ->where('role', 'user')
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'type' => 'owner',
                'title' => $user->name ?? 'Pemilik',
                'subtitle' => collect([$user->email, $user->phone])->filter()->join(' · '),
                'url' => route('admin.users.show', $user->id),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'query' => $term,
            'data' => [
                'bookings' => $bookings,
                'pets' => $pets,
                'owners' => $owners,
            ],
        ]);
    }
}
