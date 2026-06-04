<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Models\MedicalRecord;
use App\Models\Booking;
use App\Services\ImageService;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected FirebaseService $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
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

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        if ($request->has('fcm_token')) {
            \App\Models\DeviceToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'token' => $request->input('fcm_token'),
                ],
                [
                    'device_type' => $request->input('device_type', 'android'),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'phone' => $user->phone,
                ],
            ],
        ]);
    }

    /**
     * Register with email and password (direct)
     */
    public function registerDirect(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'fcm_token' => 'nullable|string',
        ]);

        // Check if user already exists
        $existingUser = User::where('email', $request->input('email'))->first();

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists with this email',
            ], 409);
        }

        // Create new user
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => 'user',
            'phone' => $request->input('phone'),
        ]);

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        if ($request->has('fcm_token')) {
            \App\Models\DeviceToken::create([
                'user_id' => $user->id,
                'token' => $request->input('fcm_token'),
                'device_type' => $request->input('device_type', 'android'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'phone' => $user->phone,
                ],
            ],
        ]);
    }

    /**
     * Login with Firebase ID Token
     */
    public function firebaseLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'fcm_token' => 'nullable|string',
        ]);

        $idToken = $request->input('id_token');

        // Verify Firebase ID Token
        $firebaseUser = $this->firebaseService->verifyIdToken($idToken);

        if (!$firebaseUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Firebase token',
            ], 401);
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

        if (!$user) {
            // Create new user
            $user = User::create([
                'firebase_uid' => $firebaseUser['uid'],
                'name' => $firebaseUser['name'] ?? explode('@', $firebaseUser['email'])[0],
                'email' => $firebaseUser['email'],
                'role' => 'user',
                'photo' => $firebaseUser['picture'] ?? null,
            ]);
        }

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        if ($request->has('fcm_token')) {
            \App\Models\DeviceToken::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'token' => $request->input('fcm_token'),
                ],
                [
                    'device_type' => $request->input('device_type', 'android'),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'phone' => $user->phone,
                ],
            ],
        ]);
    }

    /**
     * Register with Firebase (after Firebase Auth on client)
     */
    public function register(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'fcm_token' => 'nullable|string',
        ]);

        $idToken = $request->input('id_token');

        // Verify Firebase ID Token
        $firebaseUser = $this->firebaseService->verifyIdToken($idToken);

        if (!$firebaseUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Firebase token',
            ], 401);
        }

        // Check if user already exists
        $existingUser = User::where('firebase_uid', $firebaseUser['uid'])->first();

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists. Please login instead.',
            ], 409);
        }

        // Create new user
        $user = User::create([
            'firebase_uid' => $firebaseUser['uid'],
            'name' => $request->input('name', $firebaseUser['name'] ?? 'User'),
            'email' => $firebaseUser['email'],
            'role' => 'user',
            'phone' => $request->input('phone'),
            'photo' => $firebaseUser['picture'] ?? null,
        ]);

        // Create Sanctum token
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Store FCM device token if provided
        if ($request->has('fcm_token')) {
            \App\Models\DeviceToken::create([
                'user_id' => $user->id,
                'token' => $request->input('fcm_token'),
                'device_type' => $request->input('device_type', 'android'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'photo' => $user->photo,
                    'phone' => $user->phone,
                ],
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
            \App\Models\DeviceToken::where('token', $request->input('fcm_token'))->delete();
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

        return response()->json([
            'success' => true,
            'data' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'photo'      => $user->photo,
                'phone'      => $user->phone,
                'firebase_uid' => $user->firebase_uid,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
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

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'photo'      => $user->photo,
                'phone'      => $user->phone,
                'firebase_uid' => $user->firebase_uid,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Send password reset code (forgot password)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->input('email');
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $code, 'created_at' => now()]
        );

        try {
            Mail::to($email)->send(new PasswordResetMail($code, $email));
        } catch (\Exception $e) {
            // Mail failed — do not block the flow
        }

        return response()->json([
            'success' => true,
            'message' => 'If your email is registered, you will receive a password reset code.'
        ]);
    }

    /**
     * Verify reset code without changing password yet
     */
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->where('token', $request->input('code'))
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 400);
        }

        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 15) {
            DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();
            return response()->json([
                'success' => false,
                'message' => 'Reset code has expired. Please request a new one.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reset code is valid',
        ]);
    }

    /**
     * Reset password with code
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $request->input('email');
        $code = $request->input('code');

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $code)
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 400);
        }

        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 15) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Reset code has expired. Please request a new one.',
            ], 400);
        }

        $user = \App\Models\User::where('email', $email)->first();
        $user->update(['password' => Hash::make($request->input('password'))]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

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

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'photo' => $user->photo,
                'phone' => $user->phone,
                'firebase_uid' => $user->firebase_uid,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
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
