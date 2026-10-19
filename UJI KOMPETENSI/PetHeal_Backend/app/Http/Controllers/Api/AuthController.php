<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\FirebaseTokenException;
use App\Models\User;
use App\Models\DeviceToken;
use App\Models\MedicalRecord;
use App\Models\Booking;
use App\Services\ImageService;
use App\Services\FirebaseService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected FirebaseService $firebaseService;

    protected OtpService $otpService;

    public function __construct(FirebaseService $firebaseService, OtpService $otpService)
    {
        $this->firebaseService = $firebaseService;
        $this->otpService = $otpService;
    }

    private function syncDeviceToken(Request $request, User $user): void
    {
        $token = $request->input('fcm_token');

        if (!$token) {
            return;
        }

        // PHASE 3 (V-04): keep device_type consistent with the validated
        // endpoint (android/ios/web), default android. Login is also the
        // legitimate device-handover path, so reassign is allowed here.
        $deviceType = $request->input('device_type', 'android');
        if (!in_array($deviceType, ['android', 'ios', 'web'], true)) {
            $deviceType = 'android';
        }

        DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType,
            ]
        );
    }

    /**
     * Envelope error verifikasi Firebase token: status + message jelas
     * + `code` stabil untuk keputusan client (retry token baru vs tunggu).
     */
    private function firebaseTokenError(FirebaseTokenException $e)
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'code' => $e->getErrorCode(),
        ], $e->getStatus());
    }

    /**
     * Resolve clinic from slug string, return Clinic model or null.
     */
    private function resolveClinic(?string $slug): ?\App\Models\Clinic
    {
        if (!$slug) {
            return null;
        }
        return \App\Models\Clinic::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Build the user array for auth responses, including clinic object.
     */
    private function userResponse(User $user): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'photo' => $user->photo,
            'phone' => $user->phone,
        ];

        // Add clinic object (null for super_admin without clinic)
        if ($user->clinic) {
            $data['clinic'] = [
                'id' => $user->clinic->id,
                'name' => $user->clinic->name,
                'slug' => $user->clinic->slug,
                'logo_url' => $user->clinic->logo_url,
                'primary_color' => $user->clinic->primary_color,
            ];
        } else {
            $data['clinic'] = null;
        }

        return $data;
    }

    /**
     * Login with email and password (direct)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'fcm_token' => 'nullable|string',
            'clinic_slug' => 'nullable|string',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        // If clinic_slug provided, validate user belongs to that clinic
        $clinic = $this->resolveClinic($request->input('clinic_slug'));
        if ($clinic && $user->clinic_id && $user->clinic_id !== $clinic->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke klinik ini.',
            ], 403);
        }

        // PHASE 3 (F-03): suspended clinic stays suspended at login too.
        // (Middleware enforces this on every call; login mirrors it so the
        // client gets a clear 403 instead of a token that never works.)
        // super_admin exempt (no clinic binding).
        if ($user->role !== 'super_admin' && $user->clinic_id && !$user->clinic?->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Klinik Anda sedang nonaktif.',
            ], 403);
        }

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        $this->syncDeviceToken($request, $user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => $this->userResponse($user),
            ],
        ]);
    }

    /**
     * Register with email and password (direct).
     *
     * @deprecated Digantikan alur OTP email: /auth/register/request-otp lalu
     * /auth/register/verify-otp. Endpoint tetap ada supaya aplikasi versi lama
     * mendapat pesan jelas (410) alih-alih 404.
     */
    public function registerDirect(Request $request)
    {
        return response()->json([
            'success' => false,
            'code' => 'use_otp_flow',
            'message' => 'Pendaftaran email sekarang memakai kode OTP. Minta kode lewat /api/auth/register/request-otp.',
        ], 410);
    }

    /**
     * Pendaftaran email langkah 1: kirim kode OTP ke email pendaftar.
     *
     * Akun BELUM dibuat di sini. Password hanya disimpan sebagai hash di baris
     * OTP (payload), dan user baru dibuat setelah kode diverifikasi.
     */
    public function requestRegisterOtp(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'clinic_slug' => 'nullable|string',
        ]);

        $email = $this->otpService->normalize($validated['email']);

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'code' => 'email_taken',
                'message' => 'Email sudah terdaftar. Silakan masuk atau pakai email lain.',
            ], 409);
        }

        $clinic = $this->resolveClinic($validated['clinic_slug'] ?? null);
        if (!empty($validated['clinic_slug']) && !$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'Klinik tidak ditemukan atau tidak aktif.',
            ], 422);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $email,
            'password_hash' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'clinic_id' => $clinic?->id,
            'role' => 'user',
        ];

        $code = $this->otpService->issue($email, OtpService::PURPOSE_REGISTER, $payload);
        $this->otpService->send($email, OtpService::PURPOSE_REGISTER, $code);

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP dikirim ke email Anda. Cek juga folder Spam/Promosi.',
            'data' => [
                'email' => $email,
                'expires_in_minutes' => $this->otpService->ttlMinutes(OtpService::PURPOSE_REGISTER),
            ],
        ]);
    }

    /**
     * Kirim ulang kode pendaftaran tanpa user mengisi form lagi. Memakai
     * payload yang tersimpan; kalau tidak ada, sesi dianggap kedaluwarsa.
     */
    public function resendRegisterOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = $this->otpService->normalize($request->input('email'));
        $payload = $this->otpService->payload($email, OtpService::PURPOSE_REGISTER);

        if ($payload === []) {
            return response()->json([
                'success' => false,
                'code' => 'otp_session_expired',
                'message' => 'Sesi pendaftaran sudah berakhir. Silakan isi form pendaftaran lagi.',
            ], 400);
        }

        $code = $this->otpService->issue($email, OtpService::PURPOSE_REGISTER, $payload);
        $this->otpService->send($email, OtpService::PURPOSE_REGISTER, $code);

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP baru sudah dikirim.',
            'data' => [
                'email' => $email,
                'expires_in_minutes' => $this->otpService->ttlMinutes(OtpService::PURPOSE_REGISTER),
            ],
        ]);
    }

    /**
     * Pendaftaran email langkah 2: verifikasi kode lalu buat akun.
     *
     * Bentuk respons SAMA dengan endpoint register lama (token + user) supaya
     * aplikasi Android memakai jalur penyimpanan sesi yang sudah ada.
     */
    public function verifyRegisterOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'fcm_token' => 'nullable|string',
        ]);

        $email = $this->otpService->normalize($request->input('email'));
        $result = $this->otpService->verify($email, OtpService::PURPOSE_REGISTER, $request->input('code'));

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        $payload = $result['payload'];

        if (empty($payload['email']) || empty($payload['password_hash'])) {
            $this->otpService->consume($email, OtpService::PURPOSE_REGISTER);

            return response()->json([
                'success' => false,
                'message' => 'Data pendaftaran tidak ditemukan. Silakan daftar ulang.',
            ], 400);
        }

        // Email bisa saja sudah terdaftar setelah OTP dibuat (pendaftaran paralel).
        if (User::where('email', $email)->exists()) {
            $this->otpService->consume($email, OtpService::PURPOSE_REGISTER);

            return response()->json([
                'success' => false,
                'code' => 'email_taken',
                'message' => 'Email sudah terdaftar. Silakan masuk atau pakai email lain.',
            ], 409);
        }

        $user = User::create([
            'name' => $payload['name'] ?? '',
            'email' => $email,
            'password' => $payload['password_hash'],
            'role' => $payload['role'] ?? 'user',
            'phone' => $payload['phone'] ?? null,
            'clinic_id' => $payload['clinic_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        $this->otpService->consume($email, OtpService::PURPOSE_REGISTER);
        $this->syncDeviceToken($request, $user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $user->createToken('mobile-app')->plainTextToken,
                'user' => $this->userResponse($user),
            ],
        ]);
    }

    /**
     * Login with Firebase ID Token
     */
    public function firebaseLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string|max:8192',
            'fcm_token' => 'nullable|string',
            'clinic_slug' => 'nullable|string',
        ]);

        $idToken = $request->input('id_token');

        // Verify Firebase ID Token — gagal verifikasi melempar error berkode
        // (expired / malformed / project beda / layanan keys tidak tersedia).
        try {
            $firebaseUser = $this->firebaseService->verifyIdToken($idToken);
        } catch (FirebaseTokenException $e) {
            return $this->firebaseTokenError($e);
        }

        // Find or create user — search by firebase_uid first, then by email
        $user = User::where('firebase_uid', $firebaseUser['uid'])->first();

        if (!$user && $firebaseUser['email']) {
            // User may have registered with email/password before; link the firebase_uid
            $user = User::where('email', $firebaseUser['email'])->first();
            if ($user) {
                $user->firebase_uid = $firebaseUser['uid'];
                if (!$user->photo && !empty($firebaseUser['picture'])) {
                    $user->photo = $firebaseUser['picture'];
                }
                $user->save();
            }
        }

        // If user exists and clinic_slug provided, validate clinic match
        $clinic = $this->resolveClinic($request->input('clinic_slug'));
        if ($user && $clinic && $user->clinic_id && $user->clinic_id !== $clinic->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke klinik ini.',
            ], 403);
        }

        if (!$user) {
            // Token valid tapi tanpa email → tidak bisa bikin akun (users.email NOT NULL)
            if (!$firebaseUser['email']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Google ini tidak memiliki email sehingga tidak dapat digunakan.',
                    'code' => 'firebase_email_missing',
                ], 422);
            }

            // Create new user
            $user = User::create([
                'firebase_uid' => $firebaseUser['uid'],
                'name' => $firebaseUser['name'] ?? explode('@', $firebaseUser['email'])[0],
                'email' => $firebaseUser['email'],
                'role' => 'user',
                'photo' => $firebaseUser['picture'] ?? null,
                'clinic_id' => $clinic?->id,
            ]);
        }

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        $this->syncDeviceToken($request, $user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => $this->userResponse($user),
            ],
        ]);
    }

    /**
     * Register with Firebase (after Firebase Auth on client)
     */
    public function register(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string|max:8192',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'fcm_token' => 'nullable|string',
            'clinic_slug' => 'nullable|string',
        ]);

        $idToken = $request->input('id_token');

        // Verify Firebase ID Token (error berkode, lihat firebaseLogin)
        try {
            $firebaseUser = $this->firebaseService->verifyIdToken($idToken);
        } catch (FirebaseTokenException $e) {
            return $this->firebaseTokenError($e);
        }

        // Check if user already exists
        $existingUser = User::where('firebase_uid', $firebaseUser['uid'])->first();

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists. Please login instead.',
            ], 409);
        }

        // Token valid tapi tanpa email → users.email NOT NULL, tolak dengan jelas
        if (!$firebaseUser['email']) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Google ini tidak memiliki email sehingga tidak dapat digunakan.',
                'code' => 'firebase_email_missing',
            ], 422);
        }

        // Resolve clinic if provided
        $clinic = $this->resolveClinic($request->input('clinic_slug'));
        if ($request->input('clinic_slug') && !$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'Klinik tidak ditemukan atau tidak aktif.',
            ], 422);
        }

        // Create new user
        $user = User::create([
            'firebase_uid' => $firebaseUser['uid'],
            'name' => $request->input('name', $firebaseUser['name'] ?? 'User'),
            'email' => $firebaseUser['email'],
            'role' => 'user',
            'phone' => $request->input('phone'),
            'photo' => $firebaseUser['picture'] ?? null,
            'clinic_id' => $clinic?->id,
        ]);

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        $this->syncDeviceToken($request, $user);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'user' => $this->userResponse($user),
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Remove FCM token if provided
        if ($request->has('fcm_token')) {
            DeviceToken::where('user_id', $request->user()->id)
                ->where('token', $request->input('fcm_token'))
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get current user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        $data = [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'photo'      => $user->photo,
            'phone'      => $user->phone,
            'firebase_uid' => $user->firebase_uid,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        // Add clinic info
        if ($user->clinic) {
            $data['clinic'] = [
                'id' => $user->clinic->id,
                'name' => $user->clinic->name,
                'slug' => $user->clinic->slug,
                'logo_url' => $user->clinic->logo_url,
                'primary_color' => $user->clinic->primary_color,
            ];
        } else {
            $data['clinic'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'photo' => 'sometimes|nullable|string',
        ]);

        $user = $request->user();

        if ($request->has('name')) {
            $user->name = $request->input('name');
        }

        if ($request->has('phone')) {
            $user->phone = $request->input('phone');
        }

        if ($request->has('photo')) {
            $user->photo = $request->input('photo');
        }

        $user->save();

        $data = [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'photo'      => $user->photo,
            'phone'      => $user->phone,
            'firebase_uid' => $user->firebase_uid,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($user->clinic) {
            $data['clinic'] = [
                'id' => $user->clinic->id,
                'name' => $user->clinic->name,
                'slug' => $user->clinic->slug,
                'logo_url' => $user->clinic->logo_url,
                'primary_color' => $user->clinic->primary_color,
            ];
        } else {
            $data['clinic'] = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Kirim kode reset kata sandi.
     *
     * Selalu membalas sukses supaya keberadaan email tidak bisa ditebak dari
     * luar (anti-enumeration), sama seperti perilaku sebelumnya.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $this->otpService->normalize($request->input('email'));

        if (User::where('email', $email)->exists()) {
            $code = $this->otpService->issue($email, OtpService::PURPOSE_RESET);
            $this->otpService->send($email, OtpService::PURPOSE_RESET, $code);
        }

        return response()->json([
            'success' => true,
            'message' => 'If your email is registered, you will receive a password reset code.'
        ]);
    }

    /**
     * Periksa kode reset tanpa mengubah kata sandi dulu (dipakai layar
     * verifikasi kode di aplikasi).
     */
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        $result = $this->otpService->verify(
            $request->input('email'),
            OtpService::PURPOSE_RESET,
            $request->input('code')
        );

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reset code is valid',
        ]);
    }

    /**
     * Reset kata sandi memakai kode OTP email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $this->otpService->normalize($request->input('email'));

        $result = $this->otpService->verify($email, OtpService::PURPOSE_RESET, $request->input('code'));

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->otpService->consume($email, OtpService::PURPOSE_RESET);

            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 400);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);
        $this->otpService->consume($email, OtpService::PURPOSE_RESET);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
        ]);
    }

    /**
     * Upload profile photo for the current user
     */
    public function uploadProfilePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $user = $request->user();

        if ($user->photo && !filter_var($user->photo, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($user->photo);
        }

        $fileName = time() . '_' . uniqid() . '.jpg';
        $relativePath = ImageService::process(
            $request->file('photo'),
            'users/' . $fileName,
            800,
            80
        );

        $user->photo = $relativePath;
        $user->save();

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'photo' => $user->photo,
            'phone' => $user->phone,
            'firebase_uid' => $user->firebase_uid,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($user->clinic) {
            $data['clinic'] = [
                'id' => $user->clinic->id,
                'name' => $user->clinic->name,
                'slug' => $user->clinic->slug,
                'logo_url' => $user->clinic->logo_url,
                'primary_color' => $user->clinic->primary_color,
            ];
        } else {
            $data['clinic'] = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Delete account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Delete related data in correct order to respect foreign key constraints
        // 1. Delete medical records first (references bookings and pets)
        MedicalRecord::whereHas('booking', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->delete();
        
        // 2. Delete bookings (references pets)
        Booking::where('user_id', $user->id)->delete();
        
        // 3. Delete pets
        $user->pets()->delete();
        
        // 4. Delete device tokens
        $user->deviceTokens()->delete();

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}
