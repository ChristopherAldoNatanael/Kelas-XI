<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Schedule;

class DashboardController extends Controller
{
    /**
     * Return lightweight dashboard summary (counts only) with short caching
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            // OPTIMIZED: Cache for 10 minutes to reduce frequent counting on large tables.
            $summary = Cache::remember('dashboard_summary_v2', 600, function () {
                return [
                    // These counts are generally fast
                    'users_count' => User::count(),
                    'teachers_count' => Teacher::count(),
                    'subjects_count' => Subject::count(),
                    // This can be slow on very large tables
                    'schedules_count' => Schedule::count(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Dashboard summary loaded from cache.',
                'data' => $summary,
            ], 200);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Dashboard summary error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
