<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TeacherSubstitutionController;
use App\Http\Controllers\Api\EmptyClassroomController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DropdownController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\OptimizedController;
use App\Http\Controllers\Api\KehadiranController;
use App\Http\Controllers\Api\TeacherAttendanceController;
use App\Http\Controllers\Api\SiswaKehadiranController;
use App\Http\Controllers\Api\SiswaKehadiranGuruController;
use App\Http\Controllers\Api\KurikulumController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Test endpoint
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

// CLEAN weekly schedule endpoint - no middleware at all
Route::get('/jadwal-siswa', [ScheduleController::class, 'myWeeklyScheduleManualAuth']);

// SUPER SIMPLE TEST - Manual token check without Sanctum middleware
Route::get('/test-auth-manual', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'No token'], 401);
        }

        // Manual token lookup
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
        }

        $user = $accessToken->tokenable;

        return response()->json([
            'success' => true,
            'message' => 'Auth working (manual)',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// SUPER SIMPLE TEST - Auth only
Route::middleware('auth:sanctum')->get('/test-auth', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Auth working',
        'user_id' => $request->user()->id ?? 'no user'
    ]);
});

// Debug endpoint for schedule issues
Route::get('/debug-schedule', function () {
    try {
        $schedules = \App\Models\Schedule::select('id', 'hari', 'kelas', 'mata_pelajaran')->limit(5)->get();
        return response()->json([
            'success' => true,
            'count' => $schedules->count(),
            'data' => $schedules
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
});

// Lightweight dashboard summary (public for speed; add auth if needed)
Route::get('dashboard/summary', [DashboardController::class, 'summary']);

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
});

// Public routes (no authentication required for testing)
Route::apiResource('subjects', SubjectController::class);
Route::get('subjects-with-teachers', [SubjectController::class, 'getSubjectsWithTeachers']);
Route::get('subjects/{id}/teachers', [SubjectController::class, 'getTeachersBySubject']);
Route::apiResource('teachers', TeacherController::class);

// Dropdown routes untuk Android app - OPTIMIZED throttling
Route::middleware('throttle:100,1')->prefix('dropdown')->group(function () {
    Route::get('subjects', [DropdownController::class, 'getSubjects']);
    Route::get('subjects/{id}/teachers', [DropdownController::class, 'getTeachersBySubject']);
    Route::get('all', [DropdownController::class, 'getAllDropdownData']);
    Route::get('classes', [DropdownController::class, 'getClasses']);
});

// OPTIMIZED: Lightweight endpoint khusus Android dengan rate limiting ketat
Route::middleware('throttle:80,1')->group(function () {
    Route::get('schedules-mobile', [ScheduleController::class, 'indexMobile']);
    Route::get('jadwal/hari-ini', [ScheduleController::class, 'todayMobile']);
    Route::get('schedules', [ScheduleController::class, 'index']);
});

// OPTIMIZED: Public endpoints dengan throttling lebih ketat
Route::middleware('throttle:60,1')->group(function () {
    Route::get('jadwal/hari-ini-public', [ScheduleController::class, 'todayMobile']);
    Route::get('dropdown/classes-public', [DropdownController::class, 'getClasses']);
    Route::get('schedules-public', [ScheduleController::class, 'index']);
});

// TEMPORARY: Emergency test route - completely bypass ScheduleController
Route::get('test-weekly-schedule', function () {
    return 'Emergency test response - server is working';
});

// TEMPORARY: Test route without middleware
Route::get('siswa/weekly-schedule-test', [ScheduleController::class, 'myWeeklySchedule']);

// TEMPORARY: Test route with ONLY auth (no circuit breaker or role)
Route::middleware('auth:sanctum')->get('siswa/weekly-schedule-auth-only', [ScheduleController::class, 'myWeeklySchedule']);

// NEW: Manual auth route for siswa weekly schedule (bypass Sanctum middleware bug)
Route::get('siswa/weekly-schedule', [ScheduleController::class, 'myWeeklyScheduleManualAuth']);

// NEW: Weekly schedule with teacher attendance status for JadwalScreen
// CRITICAL: Must be outside middleware group to prevent server crash
Route::get('siswa/weekly-schedule-attendance', [ScheduleController::class, 'myWeeklyScheduleWithAttendanceManualAuth']);

// ===============================================
// ULTRA LIGHTWEIGHT ROUTES - NO MIDDLEWARE (Manual Auth)
// These routes bypass Sanctum middleware bugs and circuit breaker
// ===============================================
Route::get('siswa/kehadiran-guru/today', [SiswaKehadiranGuruController::class, 'todaySchedule']);
Route::post('siswa/kehadiran-guru/submit', [SiswaKehadiranGuruController::class, 'submitKehadiran']);
Route::get('siswa/kehadiran-guru/riwayat', [SiswaKehadiranGuruController::class, 'riwayat']);

// CRITICAL: These routes MUST be outside middleware group to prevent server crash
// They use manual auth inside the controller
Route::get('siswa/kehadiran/today', [SiswaKehadiranController::class, 'todaySchedule']);
Route::post('siswa/kehadiran', [SiswaKehadiranController::class, 'submitAttendance']);
Route::get('siswa/kehadiran/riwayat', [SiswaKehadiranController::class, 'riwayat']);

// Protected Routes (Require Authentication) - With Circuit Breaker
Route::middleware(['auth:sanctum', 'circuit.breaker'])->group(function () {

    // User Management (Admin Only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('classes', ClassController::class);
        // Route::apiResource('teachers', TeacherController::class); // Sudah ada di public routes
        // Route::apiResource('classrooms', ClassroomController::class); // Sudah ada di public routes
        // Route::apiResource('schedules', ScheduleController::class); // Sudah ada di public routes
    });

    // Additional schedule routes
    Route::get('schedules/teachers-by-subject', [ScheduleController::class, 'getTeachersBySubject']);

    // Kurikulum Routes
    Route::middleware('role:admin,kurikulum')->group(function () {
        Route::get('schedules', [ScheduleController::class, 'index']);
        Route::get('schedules/{id}', [ScheduleController::class, 'show']);
        Route::put('schedules/{id}', [ScheduleController::class, 'update']);
    });

    // Kepala Sekolah Routes
    Route::middleware('role:admin,kepala-sekolah')->group(function () {});

    // Siswa Routes - Optimized endpoints
    Route::middleware('role:admin,siswa')->group(function () {
        // NEW: Auto-load schedule based on user's class_id
        Route::get('siswa/my-schedule', [ScheduleController::class, 'myClassSchedule']);
        Route::get('siswa/today-schedule', [ScheduleController::class, 'myTodaySchedule']);
        Route::get('siswa/weekly-schedule', [ScheduleController::class, 'myWeeklySchedule']); // ✅ BARU
        Route::get('siswa/weekly-schedule-simple', [ScheduleController::class, 'myWeeklyScheduleSimple']); // TEST

        // NOTE: siswa/kehadiran/* routes moved outside middleware group (above)
        // They use manual auth to prevent server crashes

        // SUPER LIGHTWEIGHT ENDPOINTS untuk Android App - CRITICAL FIX
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('siswa/jadwal-hari-ini', [ScheduleController::class, 'siswaJadwalHariIni']); // Ultra lightweight
            Route::get('siswa/riwayat-kehadiran', [ScheduleController::class, 'siswaRiwayatKehadiran']); // Ultra lightweight paginated
        });

        // Legacy endpoints
        Route::get('my-schedule', [ScheduleController::class, 'mySchedule']);
        Route::get('my-schedule/{day}', [ScheduleController::class, 'myScheduleByDay']);
        Route::get('my-schedule/today', [ScheduleController::class, 'todaySchedule']);
    });
});

// Teacher Attendance Routes (Protected by auth)
Route::middleware('auth:sanctum')->group(function () {
    // Teacher Attendance Management
    Route::apiResource('teacher-attendances', TeacherAttendanceController::class);
    Route::get('teacher-attendances/by-date/{date}', [TeacherAttendanceController::class, 'getByDate']);
    Route::get('teacher-attendances/by-guru/{guru_id}', [TeacherAttendanceController::class, 'getByGuru']);
});

// ===============================================
// KURIKULUM ROUTES - Lightweight endpoints with manual auth
// These routes bypass Sanctum middleware issues for better performance
// ===============================================
Route::prefix('kurikulum')->group(function () {
    // Dashboard - Overview of all classes with teacher attendance status
    Route::get('dashboard', [KurikulumController::class, 'dashboardOverview']);

    // Class Management - Sort and filter classes by teacher status
    Route::get('classes', [KurikulumController::class, 'classManagement']);

    // Get available substitute teachers for a period
    Route::get('substitutes', [KurikulumController::class, 'getAvailableSubstitutes']);

    // Assign substitute teacher to a class
    Route::post('assign-substitute', [KurikulumController::class, 'assignSubstitute']);

    // Attendance history with filters
    Route::get('history', [KurikulumController::class, 'attendanceHistory']);

    // Attendance statistics for reports
    Route::get('statistics', [KurikulumController::class, 'attendanceStatistics']);

    // Export attendance data
    Route::get('export', [KurikulumController::class, 'exportAttendance']);

    // Get students in a class
    Route::get('class/{classId}/students', [KurikulumController::class, 'getClassStudents']);

    // Filter data - classes and teachers list
    Route::get('filter/classes', [KurikulumController::class, 'getClasses']);
    Route::get('filter/teachers', [KurikulumController::class, 'getTeachers']);

    // Pending attendance management
    Route::get('pending', [KurikulumController::class, 'getPendingAttendances']);
    Route::post('confirm-attendance', [KurikulumController::class, 'confirmAttendance']);
    Route::post('bulk-confirm', [KurikulumController::class, 'bulkConfirmAttendance']);
});
