<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\MedicalRecordController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes are for the admin panel.
|
*/

// Maintenance mode check
Route::get('/maintenance', function () {
    return view('maintenance');
})->name('maintenance');

Route::get('/down.json', function () {
    return response()->json(['maintenance' => true, 'message' => 'Sedang dalam pemeliharaan'], 503);
});

// Welcome page
Route::get('/', function () {
    if (env('APP_MAINTENANCE', false)) {
        return redirect()->route('maintenance');
    }
    return view('welcome');
});

// Admin Authentication Routes (Public)
Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');

// Protected Admin Routes
Route::middleware(['admin.auth'])->prefix('admin')->name('admin.')->group(function () {
    // Logout
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Settings
    Route::get('/settings', [App\Http\Controllers\Admin\AdminAuthController::class, 'settings'])->name('settings');
    Route::post('/settings/password', [App\Http\Controllers\Admin\AdminAuthController::class, 'updatePassword'])->name('settings.password');

    // Audit Logs
    Route::get('/audit-logs', [DashboardController::class, 'auditLogs'])->name('audit-logs');

    // Users
    Route::resource('users', UserController::class);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/bookings/{id}/complete', [BookingController::class, 'complete'])->name('bookings.complete');
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{id}/send-reminder', [BookingController::class, 'sendReminder'])->name('bookings.send-reminder');
    Route::get('/export/bookings', [BookingController::class, 'exportPdf'])->name('bookings.export');

    // Doctors
    Route::resource('doctors', DoctorController::class);

    // Medical Records
    Route::get('/medical-records', [MedicalRecordController::class, 'index'])->name('medical-records.index');
    Route::get('/medical-records/create/{bookingId}', [MedicalRecordController::class, 'create'])->name('medical-records.create');
    Route::post('/medical-records', [MedicalRecordController::class, 'store'])->name('medical-records.store');
    Route::get('/medical-records/{id}', [MedicalRecordController::class, 'show'])->name('medical-records.show');
    Route::get('/medical-records/{id}/edit', [MedicalRecordController::class, 'edit'])->name('medical-records.edit');
    Route::put('/medical-records/{id}', [MedicalRecordController::class, 'update'])->name('medical-records.update');
    Route::delete('/medical-records/{id}', [MedicalRecordController::class, 'destroy'])->name('medical-records.destroy');

    // Services
    Route::resource('services', App\Http\Controllers\Admin\ServiceController::class);

    // Payments (Midtrans Integration)
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{id}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{id}/send-reminder', [PaymentController::class, 'sendReminder'])->name('payments.send-reminder');
    Route::get('/export/payments', [PaymentController::class, 'exportPdf'])->name('payments.export');
});
