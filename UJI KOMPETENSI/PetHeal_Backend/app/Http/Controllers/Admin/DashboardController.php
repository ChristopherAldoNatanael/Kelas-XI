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

        // Date range filter — only applies when user explicitly picks dates
        $hasDateFilter = $request->has('start_date') && $request->has('end_date');
        $startDate = $hasDateFilter
            ? Carbon::parse($request->get('start_date'))->startOfDay()
            : null;
        $endDate = $hasDateFilter
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : null;

        // Today's statistics — clinic scoped
        $todayBookings = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))->today()->count();
        $pendingBookings = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))->pending()->count();
        $totalPatients = Pet::when($clinicId, fn($q) => $q->whereHas('user', fn($uq) => $uq->where('clinic_id', $clinicId)))->count();
        $totalDoctors = Doctor::where('is_active', true)->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))->count();

        // Cache key includes date range and clinic
        $cacheKey = $hasDateFilter
            ? 'dashboard_stats_c' . ($clinicId ?: 'all') . '_' . $startDate->format('Ymd') . '_' . $endDate->format('Ymd')
            : 'dashboard_stats_c' . ($clinicId ?: 'all') . '_all';
        $dashboardStats = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $hasDateFilter, $clinicId) {
            // Revenue statistics — clinic scoped
            $totalRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->value('total') ?? 0;

            $todayRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->whereDate('created_at', today())
                ->value('total') ?? 0;

            $monthlyRevenue = MedicalRecord::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->value('total') ?? 0;

            // Monthly chart data
            $monthlyBookingData = $this->getMonthlyBookingData($clinicId);
            $monthlyRevenueData = $this->getMonthlyRevenueData($clinicId);
            $dailyRevenueData = $this->getDailyRevenueData($clinicId);
            $bookingStatusDistribution = $this->getBookingStatusDistribution($clinicId);

            // Payment statistics — clinic scoped
            $baseBooking = fn() => Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]));

            $unpaidBookings = (clone $baseBooking())->where('payment_status', 'unpaid')->count();
            $pendingPayment = (clone $baseBooking())->where('payment_status', 'pending')->count();
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
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->value('total') ?? 0;

            return compact(
                'totalRevenue', 'todayRevenue', 'monthlyRevenue',
                'monthlyBookingData', 'monthlyRevenueData', 'dailyRevenueData', 'bookingStatusDistribution',
                'unpaidBookings', 'pendingPayment', 'dpPaidBookings', 'paidInFull', 'failedPayments',
                'totalCollected', 'totalOutstanding'
            );
        });

        $popularDoctors = $this->getPopularDoctors($clinicId);

        $totalRevenue = $dashboardStats['totalRevenue'];
        $todayRevenue = $dashboardStats['todayRevenue'];
        $monthlyRevenue = $dashboardStats['monthlyRevenue'];
        $monthlyBookingData = $dashboardStats['monthlyBookingData'];
        $monthlyRevenueData = $dashboardStats['monthlyRevenueData'];
        $dailyRevenueData = $dashboardStats['dailyRevenueData'];
        $bookingStatusDistribution = $dashboardStats['bookingStatusDistribution'];
        $unpaidBookings = $dashboardStats['unpaidBookings'];
        $pendingPayment = $dashboardStats['pendingPayment'];
        $dpPaidBookings = $dashboardStats['dpPaidBookings'];
        $paidInFull = $dashboardStats['paidInFull'];
        $failedPayments = $dashboardStats['failedPayments'];
        $totalCollected = $dashboardStats['totalCollected'];
        $totalOutstanding = $dashboardStats['totalOutstanding'];

        // Recent bookings — clinic scoped
        $recentBookings = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'status', 'booking_date', 'booking_time', 'created_at')
            ->with(['user:id,name,email', 'pet:id,name,user_id', 'doctor:id,name,specialization'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Upcoming appointments — clinic scoped
        $upcomingAppointments = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'status', 'booking_date', 'booking_time')
            ->with(['user:id,name,phone', 'pet:id,name,user_id', 'doctor:id,name'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->where('status', 'confirmed')
            ->where('booking_date', '>=', now()->toDateString())
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(3)
            ->get();

        // Recent payments — clinic scoped
        $recentPayments = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'paid_amount', 'total_amount', 'payment_status', 'payment_type', 'remaining_amount', 'updated_at')
            ->where('paid_amount', '>', 0)
            ->with(['user:id,name', 'pet:id,name'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Bookings with outstanding balance — clinic scoped
        $outstandingBookings = Booking::select('id', 'user_id', 'pet_id', 'remaining_amount', 'payment_type')
            ->where('remaining_amount', '>', 0)
            ->whereNotIn('payment_status', ['failed', 'unpaid'])
            ->with(['user:id,name', 'pet:id,name'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->orderBy('remaining_amount', 'desc')
            ->limit(5)
            ->get();

        // Midtrans transaction count — clinic scoped.
        // PHASE 3 (B11): `payment_method` stores the selected method NAME
        // (QRIS/GoPay/...), never the literal 'midtrans', so the old counter
        // was ~always 0. Count bookings with Midtrans-processed events
        // instead (same Blade variable, corrected value).
        $clinicBookingIds = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
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
            'totalRevenue',
            'todayRevenue',
            'monthlyRevenue',
            'monthlyBookingData',
            'monthlyRevenueData',
            'dailyRevenueData',
            'bookingStatusDistribution',
            'popularDoctors',
            'recentBookings',
            'upcomingAppointments',
            // Payment data
            'unpaidBookings',
            'pendingPayment',
            'dpPaidBookings',
            'paidInFull',
            'failedPayments',
            'totalCollected',
            'totalOutstanding',
            'recentPayments',
            'outstandingBookings',
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
            $date = Carbon::now()->subMonths($i);
            $key = $date->format('Y-m');
            $labels[] = $date->format('M Y');
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
            $date = Carbon::now()->subMonths($i);
            $key = $date->format('Y-m');
            $labels[] = $date->format('M Y');
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
            $date = Carbon::now()->subDays($i);
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('D');
            $data[] = (float) ($raw[$key] ?? 0);
        }

        return compact('labels', 'data');
    }

    /**
     * Get booking status distribution — clinic scoped
     */
    private function getBookingStatusDistribution(?int $clinicId): array
    {
        $raw = Booking::when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'pending' => (int) ($raw['pending'] ?? 0),
            'confirmed' => (int) ($raw['confirmed'] ?? 0),
            'completed' => (int) ($raw['completed'] ?? 0),
            'cancelled' => (int) ($raw['cancelled'] ?? 0),
        ];
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
}