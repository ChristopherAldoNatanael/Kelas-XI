<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MedicalRecordController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PetController;
use App\Http\Controllers\Api\VaccinationController;
use App\Http\Controllers\Api\WeightRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/firebase-login', [AuthController::class, 'firebaseLogin']);
    Route::post('/auth/register-direct', [AuthController::class, 'registerDirect']);
});

// Password reset (public)
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/verify-reset-code', [AuthController::class, 'verifyResetCode']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/health', [HealthController::class, 'index']);

// Payment methods (public - no auth required)
Route::get('/payment-methods', [App\Http\Controllers\Api\PaymentMethodController::class, 'index']);

// Services (public - no auth required)
Route::get('/services', [App\Http\Controllers\Api\ServiceController::class, 'index']);
Route::get('/services/{id}', [App\Http\Controllers\Api\ServiceController::class, 'show']);

// Midtrans webhook (public - Midtrans will call this without auth token)
Route::post('/midtrans/webhook', [App\Http\Controllers\Api\MidtransWebhookController::class, 'handle'])
    ->middleware('throttle:webhook');

// Protected routes (authentication required)
Route::middleware(['throttle:api', \App\Http\Middleware\ApiAuthenticate::class])->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/profile/photo', [AuthController::class, 'uploadProfilePhoto'])->middleware('throttle:uploads');
    Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);

    // Dashboard (personal stats)
    Route::get('/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'index']);

    // Device token routes (for FCM)
    Route::post('/device-token', [DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

    // Notification history routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications', [NotificationController::class, 'clearAll']);

    // Pet routes — custom routes MUST come BEFORE apiResource
    Route::post('/pets/with-photo', [PetController::class, 'storeWithPhoto'])->middleware('throttle:uploads');
    Route::post('/pets/{id}/photo', [PetController::class, 'uploadPhoto'])->middleware('throttle:uploads');
    Route::get('/pets/{petId}/medical-records', [MedicalRecordController::class, 'getByPet']);
    Route::get('/pets/{petId}/weight-history', [WeightRecordController::class, 'index']);
    Route::post('/pets/{petId}/weight-records', [WeightRecordController::class, 'store']);
    Route::delete('/pets/{petId}/weight-records/{recordId}', [WeightRecordController::class, 'destroy']);
    Route::get('/pets/{petId}/vaccinations', [VaccinationController::class, 'index']);
    Route::post('/pets/{petId}/vaccinations', [VaccinationController::class, 'store']);
    Route::put('/pets/{petId}/vaccinations/{vaccinationId}', [VaccinationController::class, 'update']);
    Route::delete('/pets/{petId}/vaccinations/{vaccinationId}', [VaccinationController::class, 'destroy']);
    Route::apiResource('pets', PetController::class);

    // Doctor routes
    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{id}', [DoctorController::class, 'show']);
    Route::get('/doctors/{id}/slots', [DoctorController::class, 'getAvailableSlots']);
    Route::post('/doctors/{id}/reviews', [DoctorController::class, 'storeReview']);
    Route::get('/doctors/{id}/reviews', [DoctorController::class, 'getReviews']);

    // Booking routes
    Route::get('/bookings/upcoming', [BookingController::class, 'upcoming']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{id}/reschedule', [BookingController::class, 'reschedule']);
    Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show', 'destroy']);

    // Medical record routes
    Route::apiResource('medical-records', MedicalRecordController::class)->only(['index', 'show']);

    // Payment routes (Midtrans integration)
    Route::get('/payment/preflight', [App\Http\Controllers\Api\PaymentController::class, 'preflight']);
    Route::post('/payment/snap-token', [App\Http\Controllers\Api\PaymentController::class, 'createSnapToken'])->middleware('throttle:payment');
    Route::get('/payment/transaction-status/{orderId}', [App\Http\Controllers\Api\PaymentController::class, 'getTransactionStatus']);
    Route::post('/payment/remaining/{bookingId}', [App\Http\Controllers\Api\PaymentController::class, 'createRemainingPaymentSnapToken'])->middleware('throttle:payment');
    Route::get('/payment/booking/{bookingId}', [App\Http\Controllers\Api\PaymentController::class, 'getBookingPaymentStatus']);
});
