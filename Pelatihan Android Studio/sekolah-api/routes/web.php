<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TeacherSubstitutionController;
use App\Http\Controllers\Api\EmptyClassroomController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Web\WebScheduleController;
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebDashboardController;
use App\Http\Controllers\Web\WebUserController;
use App\Http\Controllers\Web\WebTeacherController;
use App\Http\Controllers\Web\WebSubjectController;
use App\Http\Controllers\Web\WebClassController;
use App\Http\Controllers\Web\WebClassroomController;
use App\Http\Controllers\Web\WebTeacherAttendanceController;
use App\Http\Controllers\Web\WebTeacherLeaveController;

// Redirect root to dashboard
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// Prevent access to API routes from web browsers
Route::group(['prefix' => 'api'], function () {
    Route::any('{any}', function () {
        if (request()->header('Accept') && str_contains(request()->header('Accept'), 'text/html')) {
            return redirect()->route('login')->with('warning', 'Akses API melalui browser tidak diizinkan. Gunakan aplikasi mobile atau API client.');
        }
        abort(404);
    })->where('any', '.*');
});

// Authentication routes
Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('web-schedules.index');
    });

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Web\WebDashboardController::class, 'index'])->name('dashboard');

    // User management routes
    // User import routes (must come before resource routes to avoid conflicts)
    Route::get('web-users/import', [\App\Http\Controllers\Web\WebUserController::class, 'import'])->name('web-users.import');
    Route::post('web-users/import', [\App\Http\Controllers\Web\WebUserController::class, 'importStore'])->name('web-users.import.store');
    Route::get('web-users/import/template/{format}', [\App\Http\Controllers\Web\WebUserController::class, 'downloadTemplate'])->name('web-users.import.template');

    Route::resource('web-users', \App\Http\Controllers\Web\WebUserController::class)->names([
        'index' => 'web-users.index',
        'create' => 'web-users.create',
        'store' => 'web-users.store',
        'show' => 'web-users.show',
        'edit' => 'web-users.edit',
        'update' => 'web-users.update',
        'destroy' => 'web-users.destroy'
    ]);
    Route::post('web-users/{id}/restore', [\App\Http\Controllers\Web\WebUserController::class, 'restore'])->name('web-users.restore');
    Route::delete('web-users/{id}/force-delete', [\App\Http\Controllers\Web\WebUserController::class, 'forceDelete'])->name('web-users.force-delete');
    Route::delete('web-users/bulk-delete', [\App\Http\Controllers\Web\WebUserController::class, 'bulkDelete'])->name('web-users.bulk-delete');
    Route::post('web-users/bulk-delete', [\App\Http\Controllers\Web\WebUserController::class, 'bulkDelete'])->name('web-users.bulk-delete.post');
    Route::delete('web-users/bulk-delete-all', [\App\Http\Controllers\Web\WebUserController::class, 'bulkDeleteAll'])->name('web-users.bulk-delete-all');
    Route::post('web-users/bulk-delete-all', [\App\Http\Controllers\Web\WebUserController::class, 'bulkDeleteAll'])->name('web-users.bulk-delete-all.post');

    // Teacher management routes
    // Teacher import routes (must come before resource routes to avoid conflicts)
    Route::get('web-teachers/import', [\App\Http\Controllers\Web\WebTeacherController::class, 'import'])->name('web-teachers.import');
    Route::post('web-teachers/import', [\App\Http\Controllers\Web\WebTeacherController::class, 'importStore'])->name('web-teachers.import.store');
    Route::get('web-teachers/import/template/{format}', [\App\Http\Controllers\Web\WebTeacherController::class, 'downloadTemplate'])->name('web-teachers.import.template');

    Route::resource('web-teachers', \App\Http\Controllers\Web\WebTeacherController::class)->names([
        'index' => 'web-teachers.index',
        'create' => 'web-teachers.create',
        'store' => 'web-teachers.store',
        'show' => 'web-teachers.show',
        'edit' => 'web-teachers.edit',
        'update' => 'web-teachers.update',
        'destroy' => 'web-teachers.destroy'
    ]);
    Route::post('web-teachers/{id}/restore', [\App\Http\Controllers\Web\WebTeacherController::class, 'restore'])->name('web-teachers.restore');
    Route::delete('web-teachers/{id}/force-delete', [\App\Http\Controllers\Web\WebTeacherController::class, 'forceDelete'])->name('web-teachers.force-delete');
    Route::delete('web-teachers/bulk-delete', [\App\Http\Controllers\Web\WebTeacherController::class, 'bulkDelete'])->name('web-teachers.bulk-delete');
    Route::post('web-teachers/bulk-delete', [\App\Http\Controllers\Web\WebTeacherController::class, 'bulkDelete'])->name('web-teachers.bulk-delete.post');
    Route::delete('web-teachers/bulk-delete-all', [\App\Http\Controllers\Web\WebTeacherController::class, 'bulkDeleteAll'])->name('web-teachers.bulk-delete-all');
    Route::post('web-teachers/bulk-delete-all', [\App\Http\Controllers\Web\WebTeacherController::class, 'bulkDeleteAll'])->name('web-teachers.bulk-delete-all.post');


    // Subject management routes
    // Subject import routes (must come before resource routes to avoid conflicts)
    Route::get('web-subjects/import', [\App\Http\Controllers\Web\WebSubjectController::class, 'import'])->name('web-subjects.import');
    Route::post('web-subjects/import', [\App\Http\Controllers\Web\WebSubjectController::class, 'importStore'])->name('web-subjects.import.store');
    Route::get('web-subjects/import/template/{format}', [\App\Http\Controllers\Web\WebSubjectController::class, 'downloadTemplate'])->name('web-subjects.import.template');

    Route::resource('web-subjects', \App\Http\Controllers\Web\WebSubjectController::class)->names([
        'index' => 'web-subjects.index',
        'create' => 'web-subjects.create',
        'store' => 'web-subjects.store',
        'show' => 'web-subjects.show',
        'edit' => 'web-subjects.edit',
        'update' => 'web-subjects.update',
        'destroy' => 'web-subjects.destroy'
    ]);
    Route::post('web-subjects/{id}/restore', [\App\Http\Controllers\Web\WebSubjectController::class, 'restore'])->name('web-subjects.restore');
    Route::delete('web-subjects/{id}/force-delete', [\App\Http\Controllers\Web\WebSubjectController::class, 'forceDelete'])->name('web-subjects.force-delete');
    Route::delete('web-subjects/bulk-delete', [\App\Http\Controllers\Web\WebSubjectController::class, 'bulkDelete'])->name('web-subjects.bulk-delete');
    Route::post('web-subjects/bulk-delete', [\App\Http\Controllers\Web\WebSubjectController::class, 'bulkDelete'])->name('web-subjects.bulk-delete.post');
    Route::delete('web-subjects/bulk-delete-all', [\App\Http\Controllers\Web\WebSubjectController::class, 'bulkDeleteAll'])->name('web-subjects.bulk-delete-all');
    Route::post('web-subjects/bulk-delete-all', [\App\Http\Controllers\Web\WebSubjectController::class, 'bulkDeleteAll'])->name('web-subjects.bulk-delete-all.post');

    // Class management routes
    Route::resource('web-classes', \App\Http\Controllers\Web\WebClassController::class)->names([
        'index' => 'web-classes.index',
        'create' => 'web-classes.create',
        'store' => 'web-classes.store',
        'show' => 'web-classes.show',
        'edit' => 'web-classes.edit',
        'update' => 'web-classes.update',
        'destroy' => 'web-classes.destroy'
    ]);
    Route::post('web-classes/{id}/restore', [\App\Http\Controllers\Web\WebClassController::class, 'restore'])->name('web-classes.restore');
    Route::delete('web-classes/{id}/force-delete', [\App\Http\Controllers\Web\WebClassController::class, 'forceDelete'])->name('web-classes.force-delete');


    // Schedule management routes
    // Schedule import routes (must come before resource routes to avoid conflicts)
    Route::get('web-schedules/import', [\App\Http\Controllers\Web\WebScheduleController::class, 'import'])->name('web-schedules.import');
    Route::post('web-schedules/import', [\App\Http\Controllers\Web\WebScheduleController::class, 'importStore'])->name('web-schedules.import.store');
    Route::get('web-schedules/import/template/{format}', [\App\Http\Controllers\Web\WebScheduleController::class, 'downloadTemplate'])->name('web-schedules.import.template');

    Route::resource('web-schedules', \App\Http\Controllers\Web\WebScheduleController::class)->names([
        'index' => 'web-schedules.index',
        'create' => 'web-schedules.create',
        'store' => 'web-schedules.store',
        'show' => 'web-schedules.show',
        'edit' => 'web-schedules.edit',
        'update' => 'web-schedules.update',
        'destroy' => 'web-schedules.destroy'
    ]);
    Route::post('web-schedules/bulk-delete', [\App\Http\Controllers\Web\WebScheduleController::class, 'bulkDelete'])->name('web-schedules.bulk-delete.post');
    Route::post('web-schedules/bulk-delete-all', [\App\Http\Controllers\Web\WebScheduleController::class, 'bulkDeleteAll'])->name('web-schedules.bulk-delete-all.post');
    Route::get('web-schedules/{schedule}/statistics', [\App\Http\Controllers\Web\WebScheduleController::class, 'statistics'])->name('web-schedules.statistics');
    Route::get('api/schedules', [\App\Http\Controllers\Web\WebScheduleController::class, 'apiIndex'])->name('schedules.api.index');

    // Teacher Attendance routes (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('teacher-attendance', [WebTeacherAttendanceController::class, 'index'])->name('teacher-attendance.index');
        Route::get('teacher-attendance/data', [WebTeacherAttendanceController::class, 'getData'])->name('teacher-attendance.data');
        Route::get('teacher-attendance/{id}', [WebTeacherAttendanceController::class, 'show'])->name('teacher-attendance.show');
        Route::get('teacher-attendance/export/pdf', [WebTeacherAttendanceController::class, 'exportPdf'])->name('teacher-attendance.export.pdf');
        Route::get('teacher-attendance/export/excel', [WebTeacherAttendanceController::class, 'exportExcel'])->name('teacher-attendance.export.excel');
        Route::get('teacher-attendance/substitute-teachers', [WebTeacherAttendanceController::class, 'getSubstituteTeachers'])->name('teacher-attendance.substitute-teachers');

        // Teacher Leave routes
        Route::get('teacher-leaves', [WebTeacherLeaveController::class, 'index'])->name('teacher-leaves.index');
        Route::get('teacher-leaves/data', [WebTeacherLeaveController::class, 'getData'])->name('teacher-leaves.data');
        Route::get('teacher-leaves/create', [WebTeacherLeaveController::class, 'create'])->name('teacher-leaves.create');
        Route::post('teacher-leaves', [WebTeacherLeaveController::class, 'store'])->name('teacher-leaves.store');
        Route::get('teacher-leaves/{id}', [WebTeacherLeaveController::class, 'show'])->name('teacher-leaves.show');
        Route::get('teacher-leaves/{id}/edit', [WebTeacherLeaveController::class, 'edit'])->name('teacher-leaves.edit');
        Route::put('teacher-leaves/{id}', [WebTeacherLeaveController::class, 'update'])->name('teacher-leaves.update');
        Route::delete('teacher-leaves/{id}', [WebTeacherLeaveController::class, 'destroy'])->name('teacher-leaves.destroy');
        Route::post('teacher-leaves/{id}/approve', [WebTeacherLeaveController::class, 'approve'])->name('teacher-leaves.approve');
        Route::post('teacher-leaves/{id}/reject', [WebTeacherLeaveController::class, 'reject'])->name('teacher-leaves.reject');
        Route::get('teacher-leaves/substitute-teachers', [WebTeacherLeaveController::class, 'getSubstituteTeachers'])->name('teacher-leaves.substitute-teachers');
    });
});
