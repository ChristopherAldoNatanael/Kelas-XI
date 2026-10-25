<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\MedicalRecordController;
use App\Http\Controllers\Admin\NotificationSettingsController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Doctor\DoctorAuthController;
use App\Http\Controllers\Doctor\DoctorBookingController;
use App\Http\Controllers\Doctor\DoctorDashboardController;
use App\Http\Controllers\Doctor\DoctorMedicalRecordController;
use App\Http\Controllers\Doctor\DoctorProfileController;
use App\Http\Controllers\Doctor\DoctorScheduleController;
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
    if (app()->isDownForMaintenance()) {
        return redirect()->route('maintenance');
    }
    return view('welcome');
});

// Admin Authentication Routes (Public)
Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login')->name('admin.login.post');

// Join Request (replaces old register).
// Pendaftaran admin dua langkah: kirim kode ke email, lalu verifikasi kode.
// Permintaan baru diteruskan ke super admin setelah email terbukti milik
// pendaftar — kode untuk dashboard admin terpisah dari kode aplikasi pet owner.
Route::get('/admin/register', [AdminAuthController::class, 'showJoinRequestForm'])->name('admin.register');
Route::post('/admin/register', [AdminAuthController::class, 'requestJoinOtp'])->middleware('throttle:otp-send')->name('admin.register.post');
Route::post('/admin/register/verifikasi', [AdminAuthController::class, 'verifyJoinOtp'])->middleware('throttle:otp-verify')->name('admin.register.verify');
Route::post('/admin/register/kirim-ulang', [AdminAuthController::class, 'resendJoinOtp'])->middleware('throttle:otp-send')->name('admin.register.resend');
Route::post('/admin/register/batalkan', [AdminAuthController::class, 'cancelJoinOtp'])->name('admin.register.cancel');

// Protected Admin Routes
Route::middleware(['admin.auth'])->prefix('admin')->name('admin.')->group(function () {
    // Logout
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    // Switch clinic (super_admin only)
    Route::post('/switch-clinic', [AdminAuthController::class, 'switchClinic'])->middleware('super_admin')->name('switch-clinic');

    // Join Requests (super_admin only)
    Route::get('/join-requests', [AdminAuthController::class, 'joinRequests'])->middleware('super_admin')->name('join-requests');
    Route::post('/join-requests/{id}/approve', [AdminAuthController::class, 'approveJoinRequest'])->middleware('super_admin')->name('join-requests.approve');
    Route::post('/join-requests/{id}/reject', [AdminAuthController::class, 'rejectJoinRequest'])->middleware('super_admin')->name('join-requests.reject');

    // Clinics CRUD (super_admin only)
    Route::middleware('super_admin')->group(function () {
        Route::get('/clinics', [App\Http\Controllers\Admin\ClinicController::class, 'index'])->name('clinics.index');
        Route::get('/clinics/create', [App\Http\Controllers\Admin\ClinicController::class, 'create'])->name('clinics.create');
        Route::post('/clinics', [App\Http\Controllers\Admin\ClinicController::class, 'store'])->name('clinics.store');
        Route::get('/clinics/{clinic}/edit', [App\Http\Controllers\Admin\ClinicController::class, 'edit'])->name('clinics.edit');
        Route::put('/clinics/{clinic}', [App\Http\Controllers\Admin\ClinicController::class, 'update'])->name('clinics.update');
        Route::post('/clinics/{clinic}/toggle-active', [App\Http\Controllers\Admin\ClinicController::class, 'toggleActive'])->name('clinics.toggle-active');
    });

    // Legacy URL: clinic profile now lives in Settings → Clinic. Keep a
    // redirect so old bookmarks and links from before the consolidation
    // still land on the editable form.
    Route::get('/clinic-profile', fn () => redirect()->route('admin.settings', ['tab' => 'klinik']))
        ->name('clinic-profile');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [DashboardController::class, 'search'])->name('search');

    // Settings
    Route::get('/settings', [App\Http\Controllers\Admin\AdminAuthController::class, 'settings'])->name('settings');
    Route::post('/settings/profile', [App\Http\Controllers\Admin\AdminAuthController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/clinic', [App\Http\Controllers\Admin\ClinicController::class, 'updateProfile'])->name('settings.clinic.update');
    // Ganti password admin wajib kode verifikasi yang dikirim ke email akun.
    Route::post('/settings/password/otp', [App\Http\Controllers\Admin\AdminAuthController::class, 'sendPasswordOtp'])->middleware('throttle:admin-otp-send')->name('settings.password.otp');
    Route::post('/settings/password/otp/cancel', [App\Http\Controllers\Admin\AdminAuthController::class, 'cancelPasswordOtp'])->name('settings.password.otp.cancel');
    Route::post('/settings/password', [App\Http\Controllers\Admin\AdminAuthController::class, 'updatePassword'])->middleware('throttle:admin-password-verify')->name('settings.password');
    // PHASE 3 (F-02): notification templates are GLOBAL (AppSetting, no
    // clinic column) — any clinic admin could rewrite push copy for ALL
    // clinics. Restricted to super_admin like join-requests/clinics.
    Route::get('/notification-settings', [NotificationSettingsController::class, 'index'])->middleware('super_admin')->name('notification-settings.index');
    Route::put('/notification-settings', [NotificationSettingsController::class, 'update'])->middleware('super_admin')->name('notification-settings.update');

    // Audit Logs
    Route::get('/audit-logs', [DashboardController::class, 'auditLogs'])->name('audit-logs');

    // Users
    Route::resource('users', UserController::class);

    // Bookings
    Route::get('/schedule', [BookingController::class, 'schedule'])->name('bookings.schedule');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/bookings/{id}/complete', [BookingController::class, 'complete'])->name('bookings.complete');
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{id}/send-reminder', [BookingController::class, 'sendReminder'])->name('bookings.send-reminder');
    Route::get('/export/bookings', [BookingController::class, 'exportPdf'])->name('bookings.export');

    // Doctors
    // PHASE 4: jadwal & cuti per tanggal. Didaftarkan sebelum resource agar
    // tidak bergantung pada urutan pencocokan `/doctors/{id}`.
    Route::get('/doctors/{id}/schedule', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'show'])->name('doctors.schedule');
    Route::post('/doctors/{id}/schedule-exceptions', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'store'])->name('doctors.schedule-exceptions.store');
    Route::delete('/doctors/{id}/schedule-exceptions/{exception}', [\App\Http\Controllers\Admin\DoctorScheduleController::class, 'destroy'])->name('doctors.schedule-exceptions.destroy');
    // Akun portal dokter: undang lewat email, cabut bila akses harus dihentikan.
    Route::post('/doctors/{id}/invite', [DoctorController::class, 'invite'])->middleware('throttle:admin-otp-send')->name('doctors.invite');
    Route::post('/doctors/{id}/revoke-account', [DoctorController::class, 'revokeAccount'])->name('doctors.revoke-account');
    Route::resource('doctors', DoctorController::class);

    // Medical Records
    Route::get('/medical-records', [MedicalRecordController::class, 'index'])->name('medical-records.index');
    Route::get('/medical-records/export/pdf', [MedicalRecordController::class, 'exportPdf'])->name('medical-records.export.pdf');
    Route::get('/medical-records/export/csv', [MedicalRecordController::class, 'exportCsv'])->name('medical-records.export.csv');
    Route::get('/medical-records/create/{bookingId}', [MedicalRecordController::class, 'create'])->name('medical-records.create');
    Route::post('/medical-records', [MedicalRecordController::class, 'store'])->name('medical-records.store');
    Route::get('/medical-records/{id}', [MedicalRecordController::class, 'show'])->name('medical-records.show');
    Route::get('/medical-records/{id}/edit', [MedicalRecordController::class, 'edit'])->name('medical-records.edit');
    Route::put('/medical-records/{id}', [MedicalRecordController::class, 'update'])->name('medical-records.update');
    Route::delete('/medical-records/{id}', [MedicalRecordController::class, 'destroy'])->name('medical-records.destroy');

    // Services
    Route::get('/services/template/csv', [App\Http\Controllers\Admin\ServiceController::class, 'downloadTemplateCsv'])->name('services.template.csv');
    Route::get('/services/template/xlsx', [App\Http\Controllers\Admin\ServiceController::class, 'downloadTemplateXlsx'])->name('services.template.xlsx');
    Route::get('/services/import/error-report', [App\Http\Controllers\Admin\ServiceController::class, 'downloadImportErrorReport'])->name('services.import.error-report');
    Route::post('/services/import', [App\Http\Controllers\Admin\ServiceController::class, 'import'])->name('services.import');
    Route::resource('services', App\Http\Controllers\Admin\ServiceController::class)->except(['show']);

    // Payments (Midtrans Integration)
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{id}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{id}/send-reminder', [PaymentController::class, 'sendReminder'])->name('payments.send-reminder');
    Route::get('/export/payments', [PaymentController::class, 'exportPdf'])->name('payments.export');
});

/*
|--------------------------------------------------------------------------
| Doctor Portal
|--------------------------------------------------------------------------
|
| Portal khusus dokter: login terpisah, aktivasi lewat undangan email, dan
| hanya data milik dokter yang login. Admin tetap mengelola klinik dari /admin.
|
*/
Route::prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/login', [DoctorAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [DoctorAuthController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('login.post');

    Route::get('/activate', [DoctorAuthController::class, 'showActivationNotice'])->name('activate');
    Route::get('/activate/{token}', [DoctorAuthController::class, 'showActivationForm'])->name('activate.token');
    Route::post('/activate/{token}', [DoctorAuthController::class, 'activate'])
        ->middleware('throttle:otp-verify')
        ->name('activate.post');

    Route::middleware('doctor.auth')->group(function () {
        Route::post('/logout', [DoctorAuthController::class, 'logout'])->name('logout');

        Route::get('/', [DoctorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/welcome', [DoctorAuthController::class, 'welcome'])->name('welcome');

        Route::get('/bookings', [DoctorBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{id}', [DoctorBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{id}/confirm', [DoctorBookingController::class, 'confirm'])->name('bookings.confirm');
        Route::post('/bookings/{id}/complete', [DoctorBookingController::class, 'complete'])->name('bookings.complete');
        Route::post('/bookings/{id}/cancel', [DoctorBookingController::class, 'cancel'])->name('bookings.cancel');

        Route::get('/medical-records', [DoctorMedicalRecordController::class, 'index'])->name('medical-records.index');
        Route::get('/medical-records/create/{bookingId}', [DoctorMedicalRecordController::class, 'create'])->name('medical-records.create');
        Route::post('/medical-records', [DoctorMedicalRecordController::class, 'store'])->name('medical-records.store');
        Route::get('/medical-records/{id}', [DoctorMedicalRecordController::class, 'show'])->name('medical-records.show');
        Route::get('/medical-records/{id}/edit', [DoctorMedicalRecordController::class, 'edit'])->name('medical-records.edit');
        Route::put('/medical-records/{id}', [DoctorMedicalRecordController::class, 'update'])->name('medical-records.update');

        Route::get('/schedule', [DoctorScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/schedule', [DoctorScheduleController::class, 'store'])->name('schedule.store');
        Route::delete('/schedule/{exceptionId}', [DoctorScheduleController::class, 'destroy'])->name('schedule.destroy');

        Route::get('/profile', [DoctorProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [DoctorProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password/otp', [DoctorProfileController::class, 'sendPasswordOtp'])
            ->middleware('throttle:doctor-otp-send')->name('profile.password.otp');
        Route::post('/profile/password/otp/cancel', [DoctorProfileController::class, 'cancelPasswordOtp'])
            ->name('profile.password.otp.cancel');
        Route::put('/profile/password', [DoctorProfileController::class, 'updatePassword'])
            ->middleware('throttle:doctor-password-verify')->name('profile.password');
    });
});
