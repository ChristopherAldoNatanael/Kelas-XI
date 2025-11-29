<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebDashboardController extends Controller
{
    public function index()
    {
        try {
            // Get real-time statistics using correct table structures
            $stats = [
                'total_users' => User::count(),
                'total_schedules' => Schedule::count(),
                'total_teachers' => Teacher::count(),
                'total_subjects' => Subject::count(),
                'total_classrooms' => 0, // Table doesn't exist yet
                'total_classes' => ClassModel::count(),
                'active_schedules' => Schedule::count(), // No status field in actual table
                'today_schedules' => Schedule::whereDate('created_at', today())->count(),
            ];

            // Get recent schedules for display
            $recent_schedules = Schedule::with(['guru:id,nama'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return view('dashboard', compact('stats', 'recent_schedules'));
        } catch (\Exception $e) {
            return view('dashboard', [
                'stats' => [
                    'total_users' => 0,
                    'total_schedules' => 0,
                    'total_teachers' => 0,
                    'total_subjects' => 0,
                    'total_classrooms' => 0,
                    'total_classes' => 0,
                    'active_schedules' => 0,
                    'today_schedules' => 0,
                ],
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getStats()
    {
        try {
            // Get real-time statistics using direct database queries
            $stats = [
                'total_users' => User::count(),
                'total_schedules' => Schedule::count(),
                'total_teachers' => User::where('role', 'guru')->count(),
                'total_subjects' => Subject::count(),
                'total_classrooms' => 8, // Static count for available rooms
                'total_classes' => ClassModel::count(),
                'active_schedules' => Schedule::where('status', 'active')->count(),
                'today_schedules' => Schedule::whereDate('created_at', today())->count(),
            ];

            return $stats;
        } catch (\Exception $e) {
            return [
                'total_users' => 0,
                'total_schedules' => 0,
                'total_teachers' => 0,
                'total_subjects' => 0,
                'total_classrooms' => 0,
                'total_classes' => 0,
                'active_schedules' => 0,
                'today_schedules' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}
