<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\FirebaseTokenException;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\DeviceToken;
use App\Models\DoctorReview;
use App\Models\MedicalRecord;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Pet;
use App\Models\Vaccination;
use App\Models\WeightRecord;
use App\Services\ImageService;
use App\Services\FirebaseService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /** Penanda di cache bahwa kode konfirmasi hapus akun sudah diverifikasi. */
    private const DELETE_VERIFIED_PREFIX = 'account-delete-verified:';

    private const DELETE_VERIFIED_TTL_MINUTES = 15;

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
                // Login pada perangkat yang sama = token hidup lagi.
                'status' => DeviceToken::STATUS_ACTIVE,
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

        // MULTI-CLINIC DISCOVERY: pasien memakai akun global, `clinic_slug`
        // tidak lagi memvalidasi kepemilikan klinik. Tenant check hanya untuk
        // staf klinik (clinic_admin/doctor/admin).
        $clinic = ($user->role ?? null) === 'user'
            ? null
            : $this->resolveClinic($request->input('clinic_slug'));
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
        // MULTI-CLINIC DISCOVERY: pasien global tidak boleh dikunci oleh
        // klinik lama pada profilnya — ia bebas memilih klinik lain saat
        // booking, jadi status klinik lama tidak menghalangi login.
        if (!in_array($user->role, ['super_admin', 'user'], true) && $user->clinic_id && !$user->clinic?->is_active) {
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

        // `clinic_slug` sengaja TIDAK divalidasi ketat lagi: akun pasien global
        // tidak terikat klinik, jadi slug asing tidak boleh menggagalkan
        // pendaftaran (klien lama tetap mengirim field ini).
        $payload = [
            'name' => $validated['name'],
            'email' => $email,
            'password_hash' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            // MULTI-CLINIC DISCOVERY: pasien mendaftar sebagai akun GLOBAL.
            // `clinic_slug` di body tetap diterima (klien lama) tetapi tidak
            // mengikat akun ke klinik — klinik dipilih saat booking.
            'clinic_id' => null,
            'role' => 'user',
        ];

        $code = $this->otpService->issue($email, OtpService::PURPOSE_REGISTER, $payload);
        $mailSent = $this->otpService->send($email, OtpService::PURPOSE_REGISTER, $code);

        return response()->json([
            'success' => true,
            'message' => $mailSent
                ? 'Kode OTP dikirim ke email Anda. Cek juga folder Spam/Promosi.'
                : 'Kode sudah dibuat, tetapi email gagal dikirim. Coba kirim ulang beberapa saat lagi.',
            'mail_sent' => $mailSent,
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
        $mailSent = $this->otpService->send($email, OtpService::PURPOSE_REGISTER, $code);

        return response()->json([
            'success' => true,
            'message' => $mailSent
                ? 'Kode OTP baru sudah dikirim.'
                : 'Kode baru sudah dibuat, tetapi email gagal dikirim. Coba lagi beberapa saat lagi.',
            'mail_sent' => $mailSent,
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
        // (staf klinik saja — pasien adalah akun global lintas klinik).
        $clinic = ($user && $user->role !== 'user')
            ? $this->resolveClinic($request->input('clinic_slug'))
            : null;
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
                // Pasien global: tidak diikat ke klinik.
                'clinic_id' => null,
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

        // MULTI-CLINIC DISCOVERY: `clinic_slug` tidak lagi mengikat akun pasien
        // dan slug asing tidak boleh menggagalkan pendaftaran (kompatibilitas
        // klien lama yang masih mengirim field tersebut).
        // Create new user
        $user = User::create([
            'firebase_uid' => $firebaseUser['uid'],
            'name' => $request->input('name', $firebaseUser['name'] ?? 'User'),
            'email' => $firebaseUser['email'],
            'role' => 'user',
            'phone' => $request->input('phone'),
            'photo' => $firebaseUser['picture'] ?? null,
            'clinic_id' => null,
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
            // Pakai pesan + status dari OtpService supaya user tahu sisa
            // percobaan, kode kedaluwarsa, atau harus minta kode baru (429).
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
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
                'message' => $result['message'],
            ], $result['status']);
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
     * Samarkan email untuk ditampilkan di UI ("di***@gmail.com") tanpa
     * membocorkan alamat lengkap ke layar yang mungkin dilihat orang lain.
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '' || $domain === '') {
            return $email;
        }

        $visible = substr($local, 0, min(2, strlen($local)));

        return $visible . str_repeat('*', max(1, strlen($local) - strlen($visible))) . '@' . $domain;
    }

    /**
     * Langkah 1 hapus akun: kirim kode konfirmasi ke email pemilik akun.
     *
     * Akun TIDAK dihapus di sini. Penghapusan baru diizinkan setelah kode
     * diverifikasi (lihat verifyDeleteOtp + deleteAccount), sehingga HP yang
     * tidak terkunci tidak bisa dipakai untuk melenyapkan data hewan.
     */
    public function requestDeleteOtp(Request $request)
    {
        $user = $request->user();
        $email = $this->otpService->normalize($user->email);

        if ($email === '') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak memiliki alamat email sehingga kode tidak dapat dikirim.',
            ], 422);
        }

        $code = $this->otpService->issue($email, OtpService::PURPOSE_DELETE_ACCOUNT, [
            'user_id' => $user->id,
        ]);
        $mailSent = $this->otpService->send($email, OtpService::PURPOSE_DELETE_ACCOUNT, $code, [
            'recipient_name' => $user->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $mailSent
                ? 'Kode konfirmasi dikirim ke email Anda. Cek juga folder Spam/Promosi.'
                : 'Kode sudah dibuat, tetapi email gagal dikirim. Coba kirim ulang beberapa saat lagi.',
            'mail_sent' => $mailSent,
            'data' => [
                'email' => $this->maskEmail($email),
                'expires_in_minutes' => $this->otpService->ttlMinutes(OtpService::PURPOSE_DELETE_ACCOUNT),
            ],
        ]);
    }

    /**
     * Langkah 2 hapus akun: verifikasi kode. Setelah valid, user punya
     * 15 menit untuk menekan tombol hapus (DELETE /auth/account).
     */
    public function verifyDeleteOtp(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $email = $this->otpService->normalize($user->email);

        $result = $this->otpService->verify(
            $email,
            OtpService::PURPOSE_DELETE_ACCOUNT,
            $request->input('code'),
            'Kode konfirmasi'
        );

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        // Kode hanya boleh dipakai oleh akun yang memintanya, walaupun
        // kebetulan email-nya sama (mis. email dipakai ulang setelah reset).
        if ((int) ($result['payload']['user_id'] ?? 0) !== (int) $user->id) {
            $this->otpService->consume($email, OtpService::PURPOSE_DELETE_ACCOUNT);

            return response()->json([
                'success' => false,
                'message' => 'Kode konfirmasi tidak berlaku untuk akun ini. Minta kode baru.',
            ], 400);
        }

        $this->otpService->consume($email, OtpService::PURPOSE_DELETE_ACCOUNT);
        Cache::put(
            self::DELETE_VERIFIED_PREFIX . $user->id,
            true,
            now()->addMinutes(self::DELETE_VERIFIED_TTL_MINUTES)
        );

        return response()->json([
            'success' => true,
            'message' => 'Kode terverifikasi. Konfirmasi hapus akun dalam '
                . self::DELETE_VERIFIED_TTL_MINUTES . ' menit.',
            'data' => [
                'expires_in_minutes' => self::DELETE_VERIFIED_TTL_MINUTES,
            ],
        ]);
    }

    /**
     * Langkah 1 ganti email: kirim kode ke ALAMAT BARU.
     *
     * Kode dikirim ke email baru (bukan email lama) karena yang perlu dibuktikan
     * adalah kepemilikan alamat baru. Kepemilikan sesi login sudah dibuktikan
     * oleh Bearer token yang dipakai memanggil endpoint ini.
     */
    public function requestEmailChangeOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = $request->user();
        $currentEmail = $this->otpService->normalize($user->email);
        $newEmail = $this->otpService->normalize($validated['email']);

        if ($newEmail === $currentEmail) {
            return response()->json([
                'success' => false,
                'code' => 'same_email',
                'message' => 'Alamat email baru sama dengan email yang sedang dipakai.',
            ], 422);
        }

        if (User::where('email', $newEmail)->exists()) {
            return response()->json([
                'success' => false,
                'code' => 'email_taken',
                'message' => 'Email ini sudah dipakai akun lain. Gunakan alamat lain.',
            ], 409);
        }

        $code = $this->otpService->issue($newEmail, OtpService::PURPOSE_EMAIL_CHANGE, [
            'user_id' => $user->id,
            'new_email' => $newEmail,
        ]);
        $mailSent = $this->otpService->send($newEmail, OtpService::PURPOSE_EMAIL_CHANGE, $code, [
            'recipient_name' => $user->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => $mailSent
                ? 'Kode konfirmasi dikirim ke alamat email baru. Cek juga folder Spam/Promosi.'
                : 'Kode sudah dibuat, tetapi email gagal dikirim. Coba kirim ulang beberapa saat lagi.',
            'mail_sent' => $mailSent,
            'data' => [
                'email' => $this->maskEmail($newEmail),
                'expires_in_minutes' => $this->otpService->ttlMinutes(OtpService::PURPOSE_EMAIL_CHANGE),
            ],
        ]);
    }

    /**
     * Langkah 2 ganti email: verifikasi kode lalu simpan alamat baru.
     * Respons memakai bentuk user yang sama dengan updateProfile supaya
     * aplikasi bisa langsung memperbarui sesi lokal.
     */
    public function verifyEmailChangeOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $newEmail = $this->otpService->normalize($validated['email']);

        $result = $this->otpService->verify(
            $newEmail,
            OtpService::PURPOSE_EMAIL_CHANGE,
            $validated['code'],
            'Kode konfirmasi'
        );

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        $payload = $result['payload'];

        if ((int) ($payload['user_id'] ?? 0) !== (int) $user->id
            || $this->otpService->normalize((string) ($payload['new_email'] ?? '')) !== $newEmail) {
            $this->otpService->consume($newEmail, OtpService::PURPOSE_EMAIL_CHANGE);

            return response()->json([
                'success' => false,
                'message' => 'Kode konfirmasi tidak berlaku untuk perubahan ini. Minta kode baru.',
            ], 400);
        }

        // Bisa saja email sudah dipakai setelah kode dikirim (race).
        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            $this->otpService->consume($newEmail, OtpService::PURPOSE_EMAIL_CHANGE);

            return response()->json([
                'success' => false,
                'code' => 'email_taken',
                'message' => 'Email ini sudah dipakai akun lain. Gunakan alamat lain.',
            ], 409);
        }

        $user->email = $newEmail;
        $user->email_verified_at = now();
        $user->save();

        // Kode untuk tujuan lain pada email lama tidak relevan lagi.
        $this->otpService->consume($newEmail, OtpService::PURPOSE_EMAIL_CHANGE);

        return response()->json([
            'success' => true,
            'message' => 'Alamat email berhasil diperbarui.',
            'data' => $this->userResponse($user->fresh()),
        ]);
    }

    /**
     * Delete account
     */
    /**
     * Hapus akun dari sisi pengguna.
     *
     * Pemilik akun boleh berhenti memakai PetHeal, tetapi riwayat klinis bukan
     * miliknya: booking, rekam medis (SOAP/diagnosis/resep), dan jejak pembayaran
     * adalah dokumen klinik yang juga dirujuk oleh laporan keuangan. Versi
     * sebelumnya menghapus semuanya lewat cascade dan menyisakan baris `payments`
     * yatim (relasi polymorphic tanpa FK), sementara jalur admin menolak hapus user
     * yang punya riwayat.
     *
     * Aturan sekarang:
     *  - Akun tanpa riwayat (belum punya hewan/booking): dihapus seperti biasa.
     *  - Akun dengan riwayat: seluruh data pribadi dihapus/dianonimkan — nama,
     *    email, telepon, foto, kredensial login, notifikasi, device token, dan
     *    hewan yang belum pernah berkunjung. Booking, rekam medis, dan pembayaran
     *    tetap disimpan agar rekam medis klinik dan laporannya utuh.
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Penghapusan data bernilai (hewan/booking) wajib lolos kode email.
        // Akun kosong (mis. akun Google yang belum terikat klinik) tetap boleh
        // dihapus langsung supaya pemulihan akun di aplikasi tidak macet.
        $hasValuableData = $user->pets()->exists() || $user->bookings()->exists();
        $verifiedKey = self::DELETE_VERIFIED_PREFIX . $user->id;

        if ($hasValuableData && !Cache::has($verifiedKey)) {
            return response()->json([
                'success' => false,
                'code' => 'delete_verification_required',
                'message' => 'Verifikasi kode email diperlukan sebelum menghapus akun.',
            ], 428);
        }

        $userId = (int) $user->id;
        $clinicId = $user->clinic_id;
        $email = $user->email;
        $hasClinicalHistory = $user->bookings()->exists();

        // Semua perubahan dalam SATU transaksi. Kalau satu langkah gagal, tidak
        // ada akun yang setengah terhapus (mis. token sudah dicabut tetapi data
        // pribadi masih utuh).
        $result = DB::transaction(function () use ($user, $userId, $hasClinicalHistory) {
            $removedPetIds = [];
            $retainedPetIds = [];
            $photoPaths = [];

            foreach ($user->pets()->get() as $pet) {
                // Hewan yang sudah pernah berkunjung tetap tersimpan: rekam medis
                // dan booking merujuknya dengan ON DELETE CASCADE.
                $hasHistory = $pet->bookings()->exists()
                    || MedicalRecord::where('pet_id', $pet->id)->exists();

                if ($hasHistory) {
                    $retainedPetIds[] = $pet->id;
                    continue;
                }

                if ($pet->photo && !str_starts_with((string) $pet->photo, 'http')) {
                    $photoPaths[] = $pet->photo;
                }

                $removedPetIds[] = $pet->id;
            }

            if ($user->photo && !filter_var($user->photo, FILTER_VALIDATE_URL)) {
                $photoPaths[] = $user->photo;
            }

            $vaccinations = $removedPetIds === []
                ? 0
                : Vaccination::whereIn('pet_id', $removedPetIds)->delete();
            $weightRecords = $removedPetIds === []
                ? 0
                : WeightRecord::whereIn('pet_id', $removedPetIds)->delete();
            $petsDeleted = $removedPetIds === []
                ? 0
                : Pet::whereIn('id', $removedPetIds)->delete();

            // Ulasan dokter adalah konten yang ditulis atas nama pengguna, bukan
            // dokumen klinik — ikut dihapus agar nama lama tidak tertinggal publik.
            $reviews = DoctorReview::where('user_id', $userId)->delete();

            $notifications = Notification::where('user_id', $userId)->delete();
            $deviceTokens = $user->deviceTokens()->delete();
            $tokens = $user->tokens()->delete();

            if (!$hasClinicalHistory && $retainedPetIds === []) {
                // Tidak ada riwayat yang perlu dijaga: hapus barisnya sepenuhnya.
                $user->delete();

                if (User::whereKey($userId)->exists()) {
                    throw new \RuntimeException('Akun gagal dihapus: baris user masih ada.');
                }

                return [
                    'mode' => 'deleted',
                    'deleted' => [
                        'pets' => $petsDeleted,
                        'bookings' => 0,
                        'medical_records' => 0,
                        'vaccinations' => $vaccinations,
                        'weight_records' => $weightRecords,
                        'notifications' => $notifications,
                        'device_tokens' => $deviceTokens,
                        'tokens' => $tokens,
                        'reviews' => $reviews,
                    ],
                    'retained' => [
                        'pets' => 0,
                        'bookings' => 0,
                        'medical_records' => 0,
                    ],
                    'photos' => $photoPaths,
                ];
            }

            // Anonimisasi: email diganti placeholder unik (kolom unique), kredensial
            // login diacak, dan semua penanda pribadi dikosongkan. Baris user tetap
            // ada karena booking/medical_records memakai FK ke tabel ini.
            $user->forceFill([
                'name' => 'Akun Dihapus',
                'email' => 'deleted-' . $userId . '@petheal.invalid',
                'phone' => null,
                'photo' => null,
                'firebase_uid' => null,
                'password' => \Illuminate\Support\Str::random(40),
                'email_verified_at' => null,
                'remember_token' => null,
            ])->save();

            $retainedBookings = Booking::where('user_id', $userId)->count();

            return [
                'mode' => 'anonymized',
                'deleted' => [
                    'pets' => $petsDeleted,
                    'bookings' => 0,
                    'medical_records' => 0,
                    'vaccinations' => $vaccinations,
                    'weight_records' => $weightRecords,
                    'notifications' => $notifications,
                    'device_tokens' => $deviceTokens,
                    'tokens' => $tokens,
                    'reviews' => $reviews,
                ],
                'retained' => [
                    'pets' => count($retainedPetIds),
                    'bookings' => $retainedBookings,
                    'medical_records' => MedicalRecord::whereIn(
                        'booking_id',
                        Booking::where('user_id', $userId)->select('id')
                    )->count(),
                ],
                'photos' => $photoPaths,
            ];
        });

        // Verifikasi hanya dianggap terpakai setelah perubahan benar-benar commit.
        Cache::forget($verifiedKey);

        $photoPaths = $result['photos'];
        $photoCount = count($photoPaths);
        unset($result['photos']);
        $result['photos_deleted'] = $photoCount;

        // File dihapus SETELAH transaksi commit, supaya kegagalan I/O tidak
        // membatalkan penghapusan yang sudah sah.
        if ($photoPaths !== []) {
            try {
                Storage::disk('public')->delete($photoPaths);
            } catch (\Throwable $e) {
                Log::warning('Gagal menghapus file foto akun yang dihapus', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Cache dashboard personal tidak boleh bertahan untuk akun baru yang
        // kebetulan mendapat id sama (mis. setelah restore database).
        Cache::forget('dashboard_user_' . $userId);

        try {
            AuditLog::create([
                'user_id' => $result['mode'] === 'deleted' ? null : $userId,
                'clinic_id' => $clinicId,
                'action' => $result['mode'] === 'deleted' ? 'account.self_delete' : 'account.self_anonymize',
                'model_type' => User::class,
                'model_id' => $userId,
                'description' => $result['mode'] === 'deleted'
                    ? "Akun {$email} (id {$userId}) dihapus; tidak ada riwayat klinis yang tersimpan."
                    : sprintf(
                        'Akun %s (id %d) meminta penghapusan data. Data pribadi dianonimkan; %d booking dan %d rekam medis tetap disimpan sebagai dokumen klinik, %d hewan tanpa riwayat dihapus.',
                        $email,
                        $userId,
                        $result['retained']['bookings'],
                        $result['retained']['medical_records'],
                        $result['deleted']['pets']
                    ),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal menulis audit hapus akun', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $result['mode'] === 'deleted'
                ? 'Akun dan seluruh datanya berhasil dihapus permanen.'
                : 'Akun berhasil dihapus. Riwayat kunjungan dan rekam medis tetap disimpan oleh klinik sesuai kewajiban penyimpanan dokumen medis.',
            'data' => $result,
        ]);
    }
}
