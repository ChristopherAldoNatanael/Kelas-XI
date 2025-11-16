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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Test endpoint
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
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
    Route::middleware('role:admin,kepala-sekolah')->group(function () {
    });

    // Siswa Routes - Optimized endpoints
    Route::middleware('role:admin,siswa')->group(function () {
        // NEW: Auto-load schedule based on user's class_id
        Route::get('siswa/my-schedule', [ScheduleController::class, 'myClassSchedule']);
        Route::get('siswa/today-schedule', [ScheduleController::class, 'myTodaySchedule']);
        Route::get('siswa/weekly-schedule', [ScheduleController::class, 'myWeeklySchedule']); // ✅ BARU


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
