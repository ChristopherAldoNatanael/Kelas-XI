<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Admin dashboard
     */
    public function index(Request $request)
    {
        // Date range filter — only applies when user explicitly picks dates
        $hasDateFilter = $request->has('start_date') && $request->has('end_date');
        $startDate = $hasDateFilter
            ? Carbon::parse($request->get('start_date'))->startOfDay()
            : null;
        $endDate = $hasDateFilter
            ? Carbon::parse($request->get('end_date'))->endOfDay()
            : null;

        // Today's statistics
        $todayBookings = Booking::today()->count();
        $pendingBookings = Booking::pending()->count();
        $totalPatients = Pet::count();
        $totalDoctors = Doctor::where('is_active', true)->count();

        // Cache key includes date range to bust per filter
        $cacheKey = $hasDateFilter
            ? 'dashboard_stats_' . $startDate->format('Ymd') . '_' . $endDate->format('Ymd')
            : 'dashboard_stats_all';
        $dashboardStats = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $hasDateFilter) {
            // Revenue statistics - using SQL SUM for better performance
            $totalRevenue = MedicalRecord::selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->value('total') ?? 0;

            $todayRevenue = MedicalRecord::selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->whereDate('created_at', today())
                ->value('total') ?? 0;

            $monthlyRevenue = MedicalRecord::selectRaw('COALESCE(SUM(cost), 0) + COALESCE(SUM(treatment_cost), 0) + COALESCE(SUM(medicine_cost), 0) as total')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->value('total') ?? 0;

            // Monthly chart data
            $monthlyBookingData = $this->getMonthlyBookingData();
            $monthlyRevenueData = $this->getMonthlyRevenueData();
            $dailyRevenueData = $this->getDailyRevenueData();
            $bookingStatusDistribution = $this->getBookingStatusDistribution();

            // Payment statistics — filtered by date range
            $unpaidBookings = Booking::where('payment_status', 'unpaid')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->count();
            $pendingPayment = Booking::where('payment_status', 'pending')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->count();
            $dpPaidBookings = Booking::where('payment_status', 'dp_paid')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->count();
            $paidInFull = Booking::where('payment_status', 'paid')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->count();
            $failedPayments = Booking::where('payment_status', 'failed')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->count();

            $totalCollected = Booking::selectRaw('COALESCE(SUM(paid_amount), 0) as total')
                ->when($hasDateFilter, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->value('total') ?? 0;
            $totalOutstanding = Booking::selectRaw('COALESCE(SUM(remaining_amount), 0) as total')
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

        $popularDoctors = $this->getPopularDoctors();

        extract($dashboardStats);

        // Recent bookings - optimized with specific columns only
        $recentBookings = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'status', 'booking_date', 'booking_time', 'created_at')
            ->with(['user:id,name,email', 'pet:id,name,user_id', 'doctor:id,name,specialization'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Upcoming appointments - optimized with specific columns only
        $upcomingAppointments = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'status', 'booking_date', 'booking_time')
            ->with(['user:id,name,phone', 'pet:id,name,user_id', 'doctor:id,name'])
            ->where('status', 'confirmed')
            ->where('booking_date', '>=', now()->toDateString())
            ->orderBy('booking_date')
            ->orderBy('booking_time')
            ->limit(3)
            ->get();

        // Recent payments - optimized
        $recentPayments = Booking::select('id', 'user_id', 'pet_id', 'doctor_id', 'paid_amount', 'total_amount', 'payment_status', 'payment_type', 'remaining_amount', 'updated_at')
            ->where('paid_amount', '>', 0)
            ->with(['user:id,name', 'pet:id,name'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Bookings with outstanding balance - optimized
        $outstandingBookings = Booking::select('id', 'user_id', 'pet_id', 'remaining_amount', 'payment_type')
            ->where('remaining_amount', '>', 0)
            ->whereNotIn('payment_status', ['failed', 'unpaid'])
            ->with(['user:id,name', 'pet:id,name'])
            ->orderBy('remaining_amount', 'desc')
            ->limit(5)
            ->get();

        // Midtrans transaction count (bookings with Midtrans method)
        $midtransTransactions = Booking::where('payment_method', 'midtrans')->count();

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
     * Get monthly bookings data for chart (last 6 months)
     */
    private function getMonthlyBookingData(): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $raw = Booking::where('booking_date', '>=', $sixMonthsAgo)
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
     * Get monthly revenue data for chart (last 6 months)
     */
    private function getMonthlyRevenueData(): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();
        $raw = MedicalRecord::where('created_at', '>=', $sixMonthsAgo)
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
     * Get daily revenue data for chart (last 7 days)
     */
    private function getDailyRevenueData(): array
    {
        $raw = MedicalRecord::where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
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
     * Get booking status distribution
     */
    private function getBookingStatusDistribution(): array
    {
        $raw = Booking::selectRaw('status, COUNT(*) as count')
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
     * Get top 5 doctors by booking count
     */
    private function getPopularDoctors(): array
    {
        $doctors = Doctor::select('id', 'name')
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
        $logs = AuditLog::with('user')->latest()->paginate(50);
        return view('admin.audit-logs', compact('logs'));
    }
}
