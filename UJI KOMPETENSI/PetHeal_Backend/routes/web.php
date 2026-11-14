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
        Route::delete('/clinics/{clinic}', [App\Http\Controllers\Admin\ClinicController::class, 'destroy'])->name('clinics.destroy');
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

    // Kategori layanan standar (global) — dasar perbandingan harga antar
    // klinik, jadi hanya super_admin yang boleh mengubah.
    Route::middleware('super_admin')->group(function () {
        Route::get('/service-categories', [App\Http\Controllers\Admin\ServiceCategoryController::class, 'index'])->name('service-categories.index');
        Route::post('/service-categories', [App\Http\Controllers\Admin\ServiceCategoryController::class, 'store'])->name('service-categories.store');
        Route::put('/service-categories/{serviceCategory}', [App\Http\Controllers\Admin\ServiceCategoryController::class, 'update'])->name('service-categories.update');
        Route::delete('/service-categories/{serviceCategory}', [App\Http\Controllers\Admin\ServiceCategoryController::class, 'destroy'])->name('service-categories.destroy');
    });

    // Audit Logs
    Route::get('/audit-logs', [DashboardController::class, 'auditLogs'])->name('audit-logs');

    // Users
    Route::resource('users', UserController::class);

    // Antrian harian. Rute aksi didaftarkan sebelum resource agar tidak
    // tertangkap oleh pola /bookings/{id}.
    Route::get('/queue', [\App\Http\Controllers\Admin\QueueController::class, 'index'])->name('queue.index');
    Route::post('/queue/call-next', [\App\Http\Controllers\Admin\QueueController::class, 'callNext'])->name('queue.call-next');
    Route::post('/queue/{id}/check-in', [\App\Http\Controllers\Admin\QueueController::class, 'checkIn'])->name('queue.check-in');
    Route::post('/queue/{id}/status', [\App\Http\Controllers\Admin\QueueController::class, 'updateStatus'])->name('queue.status');

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

    // Stok obat. Rute aksi khusus didaftarkan SEBELUM resource supaya
    // `/medicines/{id}/stock-in` tidak tertangkap sebagai {medicine} biasa.
    Route::get('/medicines/template/csv', [App\Http\Controllers\Admin\MedicineController::class, 'downloadTemplateCsv'])->name('medicines.template.csv');
    Route::get('/medicines/template/xlsx', [App\Http\Controllers\Admin\MedicineController::class, 'downloadTemplateXlsx'])->name('medicines.template.xlsx');
    Route::get('/medicines/export/csv', [App\Http\Controllers\Admin\MedicineController::class, 'exportCsv'])->name('medicines.export.csv');
    Route::get('/medicines/export/xlsx', [App\Http\Controllers\Admin\MedicineController::class, 'exportXlsx'])->name('medicines.export.xlsx');
    Route::get('/medicines/import/error-report', [App\Http\Controllers\Admin\MedicineController::class, 'downloadImportErrorReport'])->name('medicines.import.error-report');
    Route::post('/medicines/import', [App\Http\Controllers\Admin\MedicineController::class, 'import'])->name('medicines.import');
    // Aksi massal & pemusnahan. Harus SEBELUM resource supaya
    // `/medicines/bulk-destroy` tidak dicocokkan sebagai `{medicine}`.
    Route::get('/medicines/disposals', [App\Http\Controllers\Admin\MedicineController::class, 'disposals'])->name('medicines.disposals');
    Route::post('/medicines/bulk-destroy', [App\Http\Controllers\Admin\MedicineController::class, 'bulkDestroy'])->name('medicines.bulk-destroy');
    Route::delete('/medicines/destroy-all', [App\Http\Controllers\Admin\MedicineController::class, 'destroyAll'])->name('medicines.destroy-all');
    Route::post('/medicines/bulk-dispose', [App\Http\Controllers\Admin\MedicineController::class, 'bulkDispose'])->name('medicines.bulk-dispose');

    // Monitoring kedaluwarsa per batch & mutasi stok berstatus. Didaftarkan
    // sebelum resource agar `/medicines/expiry` tidak dianggap {medicine}.
    Route::get('/medicines/expiry', [\App\Http\Controllers\Admin\MedicineStockController::class, 'expiry'])->name('medicines.expiry');
    Route::get('/medicines/mutations', [\App\Http\Controllers\Admin\MedicineStockController::class, 'mutations'])->name('medicines.mutations.index');
    Route::get('/medicines/mutations/create', [\App\Http\Controllers\Admin\MedicineStockController::class, 'createMutation'])->name('medicines.mutations.create');
    Route::post('/medicines/mutations', [\App\Http\Controllers\Admin\MedicineStockController::class, 'storeMutation'])->name('medicines.mutations.store');
    Route::get('/medicines/mutations/{mutation}', [\App\Http\Controllers\Admin\MedicineStockController::class, 'showMutation'])->name('medicines.mutations.show');
    Route::post('/medicines/mutations/{mutation}/process', [\App\Http\Controllers\Admin\MedicineStockController::class, 'processMutation'])->name('medicines.mutations.process');
    Route::post('/medicines/mutations/{mutation}/complete', [\App\Http\Controllers\Admin\MedicineStockController::class, 'completeMutation'])->name('medicines.mutations.complete');
    Route::post('/medicines/mutations/{mutation}/cancel', [\App\Http\Controllers\Admin\MedicineStockController::class, 'cancelMutation'])->name('medicines.mutations.cancel');
    Route::post('/medicines/mutations/{mutation}/reverse', [\App\Http\Controllers\Admin\MedicineStockController::class, 'reverseMutation'])->name('medicines.mutations.reverse');
    Route::get('/medicines/mutations/{mutation}/document/{field}', [\App\Http\Controllers\Admin\MedicineStockController::class, 'downloadDocument'])->name('medicines.mutations.document');
    Route::post('/medicines/{id}/dispose', [App\Http\Controllers\Admin\MedicineController::class, 'dispose'])->name('medicines.dispose');
    Route::post('/medicines/{id}/stock-in', [App\Http\Controllers\Admin\MedicineController::class, 'stockIn'])->name('medicines.stock-in');
    Route::post('/medicines/{id}/adjust', [App\Http\Controllers\Admin\MedicineController::class, 'adjust'])->name('medicines.adjust');
    Route::get('/medicines/{id}/movements', [App\Http\Controllers\Admin\MedicineController::class, 'movements'])->name('medicines.movements');
    Route::resource('medicines', App\Http\Controllers\Admin\MedicineController::class)->except(['show']);

    // Payments (Midtrans Integration)
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{id}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
    // Refund manual: mencatat uang yang sudah dikembalikan ke pasien.
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('/payments/{id}/send-reminder', [PaymentController::class, 'sendReminder'])->name('payments.send-reminder');
    Route::get('/export/payments', [PaymentController::class, 'exportPdf'])->name('payments.export');

    // Pengaturan pembayaran Midtrans per klinik (VCMS).
    // Clinic Admin hanya dapat mengelola kliniknya sendiri; super_admin
    // memilih klinik terlebih dahulu lewat session context yang sama.
    Route::prefix('payment-settings')->name('payment-settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'index'])->name('index');
        Route::post('/select-clinic', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'selectClinic'])
            ->middleware('super_admin')->name('select-clinic');
        Route::post('/credentials', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'storeCredentials'])->name('credentials.store');
        Route::post('/credentials/{version}/test', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'testConnection'])
            ->whereNumber('version')->name('credentials.test');
        Route::post('/credentials/{version}/activate', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'activate'])
            ->whereNumber('version')->name('credentials.activate');
        Route::post('/deactivate', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'deactivate'])->name('deactivate');
        Route::post('/production-approval', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'approveProduction'])
            ->middleware('super_admin')->name('production.approve');
    });

    // ── Pet Shop (Penjualan Produk) ─────────────────────────────────────────
    // Domain terpisah dari layanan medis & stok obat. Semua query dibatasi
    // clinic_id oleh controller (bukan hanya menu yang disembunyikan).
    Route::prefix('pet-shop')->name('pet-shop.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PetShopDashboardController::class, 'index'])->name('dashboard');

        Route::get('/products', [\App\Http\Controllers\Admin\PetShopProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [\App\Http\Controllers\Admin\PetShopProductController::class, 'create'])->name('products.create');
        Route::post('/products', [\App\Http\Controllers\Admin\PetShopProductController::class, 'store'])->name('products.store');

        // Rute statis import/template HARUS di atas `/products/{product}`,
        // kalau tidak `products/import` akan dibaca sebagai id produk.
        Route::post('/products/import', [\App\Http\Controllers\Admin\PetShopProductController::class, 'import'])->name('products.import');
        Route::get('/products/import/error-report', [\App\Http\Controllers\Admin\PetShopProductController::class, 'downloadImportErrorReport'])->name('products.import.error-report');
        Route::get('/products/template/csv', [\App\Http\Controllers\Admin\PetShopProductController::class, 'downloadTemplateCsv'])->name('products.template.csv');
        Route::get('/products/template/xlsx', [\App\Http\Controllers\Admin\PetShopProductController::class, 'downloadTemplateXlsx'])->name('products.template.xlsx');
        Route::get('/products/export/csv', [\App\Http\Controllers\Admin\PetShopProductController::class, 'exportCsv'])->name('products.export.csv');
        Route::get('/products/export/xlsx', [\App\Http\Controllers\Admin\PetShopProductController::class, 'exportXlsx'])->name('products.export.xlsx');

        Route::get('/products/{product}', [\App\Http\Controllers\Admin\PetShopProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [\App\Http\Controllers\Admin\PetShopProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [\App\Http\Controllers\Admin\PetShopProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [\App\Http\Controllers\Admin\PetShopProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/toggle', [\App\Http\Controllers\Admin\PetShopProductController::class, 'toggle'])->name('products.toggle');
        Route::post('/variants/{variant}/stock', [\App\Http\Controllers\Admin\PetShopProductController::class, 'adjustStock'])->name('variants.stock');

        Route::get('/orders', [\App\Http\Controllers\Admin\PetShopOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [\App\Http\Controllers\Admin\PetShopOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/transition', [\App\Http\Controllers\Admin\PetShopOrderController::class, 'transition'])->name('orders.transition');
        Route::post('/refunds/{refund}/confirm', [\App\Http\Controllers\Admin\PetShopOrderController::class, 'confirmRefund'])->name('refunds.confirm');

        Route::get('/sales', [\App\Http\Controllers\Admin\PetShopSalesController::class, 'index'])->name('sales.index');
        Route::get('/sales/export', [\App\Http\Controllers\Admin\PetShopSalesController::class, 'export'])->name('sales.export');

        // Kategori global & konfigurasi merchant hanya untuk super_admin.
        Route::middleware('super_admin')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Admin\PetShopCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [\App\Http\Controllers\Admin\PetShopCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [\App\Http\Controllers\Admin\PetShopCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\PetShopCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/merchants', [\App\Http\Controllers\Admin\PetShopMerchantController::class, 'index'])->name('merchants.index');
            Route::put('/merchants/{clinic}', [\App\Http\Controllers\Admin\PetShopMerchantController::class, 'update'])->name('merchants.update');
        });
    });
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
