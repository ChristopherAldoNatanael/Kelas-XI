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

    // Teacher management routes
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

    // Subject management routes
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


    // Schedule CRUD routes
    Route::resource('web-schedules', \App\Http\Controllers\Web\WebScheduleController::class)->names([
        'index' => 'web-schedules.index',
        'create' => 'web-schedules.create',
        'store' => 'web-schedules.store',
        'show' => 'web-schedules.show',
        'edit' => 'web-schedules.edit',
        'update' => 'web-schedules.update',
        'destroy' => 'web-schedules.destroy'
    ]);
    Route::get('web-schedules/{schedule}/statistics', [\App\Http\Controllers\Web\WebScheduleController::class, 'statistics'])->name('web-schedules.statistics');
    Route::get('api/schedules', [\App\Http\Controllers\Web\WebScheduleController::class, 'apiIndex'])->name('schedules.api.index');
});
