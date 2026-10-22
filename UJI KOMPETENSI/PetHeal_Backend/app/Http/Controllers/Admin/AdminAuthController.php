<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    /** Email yang sedang menunggu kode verifikasi pendaftaran admin. */
    private const JOIN_EMAIL = 'admin_join_email';
    private const JOIN_SENT_AT = 'admin_join_otp_sent_at';

    /** Penanda kapan kode ganti sandi terakhir dikirim (untuk jeda kirim ulang). */
    private const PASSWORD_SENT_AT = 'admin_password_otp_sent_at';
    /**
     * Show login form
     */
    public function showLoginForm()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['super_admin', 'clinic_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    /**
     * Process login — supports super_admin, clinic_admin, legacy admin
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'remember' => 'nullable|boolean',
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])
            ->whereIn('role', ['super_admin', 'clinic_admin', 'admin'])
            ->first();

        if ($user && Hash::check($credentials['password'], $user->password)) {
            // PHASE 3 (F-03): suspended clinic stays suspended — refuse login
            // when the tenant's clinic is missing/inactive. super_admin exempt.
            if ($user->role !== 'super_admin') {
                $clinic = $user->clinic;
                if (!$user->clinic_id || !$clinic || !$clinic->is_active) {
                    return back()->withErrors([
                        'email' => __('auth.inactive_clinic'),
                    ])->onlyInput('email');
                }
            }

            // PHASE 4: wire the remember-me checkbox (was dead UI — the
            // credential cookie was never set).
            Auth::login($user, (bool) $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => __('auth.invalid_credentials'),
        ])->onlyInput('email');
    }

    /**
     * Show join request form (replaces old register).
     *
     * Ketika ada email yang menunggu verifikasi, halaman yang sama menampilkan
     * langkah kode akses — bukan halaman ketiga yang terpisah.
     */
    public function showJoinRequestForm(Request $request, OtpService $otp)
    {
        if (Auth::check() && in_array(Auth::user()->role, ['super_admin', 'clinic_admin', 'admin'])) {
            return redirect()->route('admin.dashboard');
        }

        $clinics = Clinic::where('is_active', true)->orderBy('name')->get();
        $pending = $this->pendingJoinVerification($request, $otp);

        return view('admin.auth.join-request', compact('clinics', 'pending'));
    }

    /**
     * Langkah 1 pendaftaran admin: validasi data, buat kode, kirim ke email.
     * Belum ada ClinicJoinRequest yang dibuat di titik ini.
     */
    public function requestJoinOtp(Request $request, OtpService $otp)
    {
        $validated = $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $clinic = Clinic::where('is_active', true)->find($validated['clinic_id']);
        if (!$clinic) {
            return back()->withErrors([
                'clinic_id' => 'Klinik ini sedang tidak aktif. Pilih klinik lain atau hubungi super admin.',
            ])->withInput();
        }

        $email = $otp->normalize($validated['email']);

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => __('auth.email_taken')])->withInput();
        }

        $alreadyPending = ClinicJoinRequest::where('email', $email)
            ->where('clinic_id', $clinic->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return back()->withErrors([
                'email' => 'Permintaan untuk email ini sudah menunggu persetujuan super admin. Tidak perlu mendaftar lagi.',
            ])->withInput();
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'clinic_id' => $clinic->id,
            // Password tidak pernah disimpan plaintext, termasuk di payload kode.
            'password_hash' => Hash::make($validated['password']),
        ];

        $code = $otp->issue($email, OtpService::PURPOSE_ADMIN_REGISTER, $payload);
        $sent = $otp->send($email, OtpService::PURPOSE_ADMIN_REGISTER, $code, [
            'clinic_name' => $clinic->name,
            'recipient_name' => $validated['name'],
        ]);

        $request->session()->put(self::JOIN_EMAIL, $email);
        $request->session()->put(self::JOIN_SENT_AT, now()->timestamp);

        return redirect()->route('admin.register')->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Kode akses admin sudah dikirim ke ' . $email . '. Kode berlaku ' . $otp->ttlMinutes(OtpService::PURPOSE_ADMIN_REGISTER) . ' menit.'
                : 'Kode sudah dibuat, tetapi email gagal dikirim. Gunakan "Kirim ulang kode" beberapa saat lagi.'
        );
    }

    /**
     * Langkah 2 pendaftaran admin: verifikasi kode lalu buat permintaan join.
     * Akun admin tetap belum dibuat — super admin yang menyetujui.
     */
    public function verifyJoinOtp(Request $request, OtpService $otp)
    {
        $request->validate([
            'code' => ['required', 'array', 'size:6'],
            'code.*' => ['required', 'digits:1'],
        ], [
            'code.required' => 'Masukkan 6 digit kode akses dari email Anda.',
            'code.size' => 'Kode akses terdiri dari 6 digit.',
            'code.*.digits' => 'Setiap kotak hanya boleh berisi satu angka.',
        ]);

        $code = implode('', $request->input('code'));
        $email = (string) $request->session()->get(self::JOIN_EMAIL, '');

        if ($email === '') {
            return redirect()->route('admin.register')
                ->with('warning', 'Sesi verifikasi tidak ditemukan. Silakan isi formulir pendaftaran lagi.');
        }

        $result = $otp->verify($email, OtpService::PURPOSE_ADMIN_REGISTER, $code, 'Kode akses admin');

        if (!$result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $payload = $result['payload'];
        if (empty($payload['email']) || empty($payload['password_hash']) || empty($payload['clinic_id'])) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.register')
                ->with('warning', 'Data pendaftaran tidak ditemukan. Silakan isi formulir lagi.');
        }

        $clinic = Clinic::find($payload['clinic_id']);
        if (!$clinic || !$clinic->is_active) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.register')
                ->with('warning', 'Klinik tujuan sudah tidak aktif. Silakan pilih klinik lain.');
        }

        // Email bisa saja terdaftar / request lain dibuat setelah kode dikirim.
        if (User::where('email', $email)->exists()) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.register')
                ->withErrors(['email' => __('auth.email_taken')]);
        }

        $alreadyPending = ClinicJoinRequest::where('email', $email)
            ->where('clinic_id', $clinic->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.login')
                ->with('success', 'Email sudah terverifikasi dan permintaan Anda masih menunggu persetujuan super admin.');
        }

        ClinicJoinRequest::create([
            'clinic_id' => $clinic->id,
            'name' => $payload['name'] ?? '',
            'email' => $email,
            'phone' => $payload['phone'] ?? null,
            'password_hash' => $payload['password_hash'],
            'status' => 'pending',
        ]);

        $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
        $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);
        $request->session()->regenerate();

        return redirect()->route('admin.login')->with(
            'success',
            'Email ' . $email . ' terverifikasi. Permintaan akses admin ' . $clinic->name . ' sudah dikirim ke super admin.'
        );
    }

    /**
     * Kirim ulang kode pendaftaran memakai payload yang tersimpan, dengan jeda
     * 60 detik supaya kuota email tidak habis karena klik berulang.
     */
    public function resendJoinOtp(Request $request, OtpService $otp)
    {
        $email = (string) $request->session()->get(self::JOIN_EMAIL, '');

        if ($email === '') {
            return redirect()->route('admin.register')
                ->with('warning', 'Sesi verifikasi sudah berakhir. Silakan isi formulir pendaftaran lagi.');
        }

        $wait = $this->resendWait($request);
        if ($wait > 0) {
            return back()->with('warning', 'Tunggu ' . $wait . ' detik sebelum meminta kode baru.');
        }

        $payload = $otp->payload($email, OtpService::PURPOSE_ADMIN_REGISTER);
        if ($payload === []) {
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.register')
                ->with('warning', 'Kode sebelumnya sudah tidak berlaku. Silakan isi formulir pendaftaran lagi.');
        }

        $clinic = Clinic::find($payload['clinic_id'] ?? null);
        $code = $otp->issue($email, OtpService::PURPOSE_ADMIN_REGISTER, $payload);
        $sent = $otp->send($email, OtpService::PURPOSE_ADMIN_REGISTER, $code, [
            'clinic_name' => $clinic?->name ?? 'PetHeal',
            'recipient_name' => $payload['name'] ?? null,
        ]);

        $request->session()->put(self::JOIN_SENT_AT, now()->timestamp);

        return back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? 'Kode akses baru sudah dikirim ke ' . $email . '.'
                : 'Kode baru dibuat, tetapi email gagal dikirim. Coba lagi beberapa saat lagi.'
        );
    }

    /** Batalkan pendaftaran yang belum terverifikasi. */
    public function cancelJoinOtp(Request $request, OtpService $otp)
    {
        $email = (string) $request->session()->get(self::JOIN_EMAIL, '');

        if ($email !== '') {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
        }

        $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

        return redirect()->route('admin.register')
            ->with('success', 'Pendaftaran dibatalkan. Data yang Anda isi tidak disimpan.');
    }

    /** @return array<string, mixed>|null */
    private function pendingJoinVerification(Request $request, OtpService $otp): ?array
    {
        $email = (string) $request->session()->get(self::JOIN_EMAIL, '');

        if ($email === '') {
            return null;
        }

        $expiresAt = $otp->activeUntil($email, OtpService::PURPOSE_ADMIN_REGISTER);
        $payload = $otp->payload($email, OtpService::PURPOSE_ADMIN_REGISTER);

        if (!$expiresAt || $payload === []) {
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);
            $request->session()->flash('warning', 'Sesi verifikasi berakhir. Silakan isi formulir pendaftaran lagi.');

            return null;
        }

        $clinic = Clinic::find($payload['clinic_id'] ?? null);

        return [
            'email' => $email,
            'name' => $payload['name'] ?? null,
            'clinic_name' => $clinic?->name ?? 'PetHeal',
            'clinic_color' => $clinic?->primary_color ?? '#18C964',
            'ttl' => $otp->ttlMinutes(OtpService::PURPOSE_ADMIN_REGISTER),
            'expires_at' => $expiresAt,
            'resend_wait' => $this->resendWait($request),
        ];
    }

    private function resendWait(Request $request): int
    {
        $sentAt = (int) $request->session()->get(self::JOIN_SENT_AT, 0);

        return max(0, 60 - (now()->timestamp - $sentAt));
    }

    /**
     * Show join requests list — clinic scoped
     */
    public function joinRequests()
    {
        $clinicId = currentClinicId();

        $requests = ClinicJoinRequest::with(['clinic', 'reviewedBy'])
            ->when($clinicId, fn($q) => $q->where('clinic_id', $clinicId))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.join-requests', compact('requests'));
    }

    /**
     * Approve a join request — clinic scoped
     */
    public function approveJoinRequest(Request $request, $id)
    {
        $clinicId = currentClinicId();

        // PHASE 5 (SA-02): approve + user create must be atomic under a row
        // lock — concurrent double-approvals previously raced past the
        // pending check (one side then hit users.email unique as a 500).
        $joinRequest = \Illuminate\Support\Facades\DB::transaction(function () use ($id, $clinicId) {
            $query = ClinicJoinRequest::where('status', 'pending')->where('id', $id);
            if ($clinicId) {
                $query->where('clinic_id', $clinicId);
            }
            $joinRequest = $query->lockForUpdate()->firstOrFail();

            // Check if email already taken by any user
            if (User::where('email', $joinRequest->email)->exists()) {
                // Auto-reject this request since email is taken
                $joinRequest->update([
                    'status' => 'rejected',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);

                return $joinRequest;
            }

            // Create user as clinic_admin
            User::create([
                'name' => $joinRequest->name,
                'email' => $joinRequest->email,
                'phone' => $joinRequest->phone,
                'password' => $joinRequest->password_hash,
                'role' => 'clinic_admin',
                'clinic_id' => $joinRequest->clinic_id,
            ]);

            $joinRequest->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $joinRequest->fresh();
        });

        if ($joinRequest->status === 'rejected') {
            return back()->withErrors(['email' => __('clinics.email_already_used')]);
        }

        return back()->with('success', __('clinics.approved_success', ['name' => $joinRequest->name]));
    }

    /**
     * Reject a join request — clinic scoped
     */
    public function rejectJoinRequest(Request $request, $id)
    {
        $clinicId = currentClinicId();
        $query = ClinicJoinRequest::where('status', 'pending')->where('id', $id);
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $joinRequest = $query->firstOrFail();

        $joinRequest->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('clinics.rejected_success', ['name' => $joinRequest->name]));
    }

    /**
     * Switch active clinic (super_admin only)
     */
    public function switchClinic(Request $request)
    {
        $clinicId = $request->input('clinic_id');

        if (empty($clinicId)) {
            // System Overview — clear clinic context
            $request->session()->forget('current_clinic_id');
            return redirect()->route('admin.dashboard')->with('success', __('clinics.system_overview_switch'));
        }

        $request->validate([
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        $request->session()->put('current_clinic_id', (int) $clinicId);

        // Preserve the schedule board flow after choosing a clinic from the
        // system-overview picker. Only a fixed internal destination is allowed.
        if ($request->input('return_to') === 'schedule') {
            return redirect()->route('admin.bookings.schedule')->with('success', __('clinics.switch_success'));
        }

        return redirect()->route('admin.dashboard')->with('success', __('clinics.switch_success'));
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    /**
     * Show settings page
     */
    public function settings(Request $request, OtpService $otp)
    {
        $clinic = currentClinic();

        $clinicAdmins = null;
        if ($clinic) {
            $clinicAdmins = User::where('clinic_id', $clinic->id)
                ->whereIn('role', ['clinic_admin', 'super_admin'])
                ->orderBy('name')
                ->get();
        }

        // One page, four tabs: akun, keamanan, klinik, tim. Tab klinik dan tim
        // hanya tersedia ketika ada konteks klinik aktif.
        $activeTab = (string) $request->query('tab', 'akun');

        // Kode ganti sandi yang masih hidup menentukan apakah tab Keamanan
        // menampilkan tombol "kirim kode" atau form berisi kolom kode.
        $me = Auth::user();
        $passwordOtp = null;
        if ($me) {
            $expiresAt = $otp->activeUntil($otp->normalize($me->email), OtpService::PURPOSE_ADMIN_PASSWORD);
            if ($expiresAt) {
                $sentAt = (int) $request->session()->get(self::PASSWORD_SENT_AT, 0);
                $passwordOtp = [
                    'expires_at' => $expiresAt,
                    'ttl' => $otp->ttlMinutes(OtpService::PURPOSE_ADMIN_PASSWORD),
                    'resend_wait' => max(0, 60 - (now()->timestamp - $sentAt)),
                ];
            }
        }

        return view('admin.settings', compact('clinic', 'clinicAdmins', 'activeTab', 'passwordOtp'));
    }

    /**
     * Update admin profile (name + email)
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
        ]);

        return back()->with('success', __('auth.profile_updated'));
    }

    /**
     * Kirim kode verifikasi ganti sandi ke email akun admin yang sedang login.
     */
    public function sendPasswordOtp(Request $request, OtpService $otp)
    {
        $admin = Auth::user();

        if (!$admin) {
            return redirect()->route('admin.login');
        }

        $clinic = currentClinic();
        $email = $otp->normalize($admin->email);

        $code = $otp->issue($email, OtpService::PURPOSE_ADMIN_PASSWORD, [
            'admin_id' => $admin->id,
            'clinic_id' => $clinic?->id,
        ]);

        $sent = $otp->send($email, OtpService::PURPOSE_ADMIN_PASSWORD, $code, [
            'clinic_name' => $clinic?->name ?? 'PetHeal',
            'recipient_name' => $admin->name,
        ]);

        $request->session()->put(self::PASSWORD_SENT_AT, now()->timestamp);

        return back()->with(
            $sent ? 'success' : 'error',
            $sent
                ? 'Kode akses admin dikirim ke ' . $email . '. Berlaku ' . $otp->ttlMinutes(OtpService::PURPOSE_ADMIN_PASSWORD) . ' menit.'
                : 'Kode gagal dikirim ke email. Periksa konfigurasi email server lalu coba lagi.'
        );
    }

    /** Batalkan kode ganti sandi yang belum dipakai. */
    public function cancelPasswordOtp(Request $request, OtpService $otp)
    {
        $admin = Auth::user();

        if ($admin) {
            $otp->consume($otp->normalize($admin->email), OtpService::PURPOSE_ADMIN_PASSWORD);
        }

        $request->session()->forget(self::PASSWORD_SENT_AT);

        return back()->with('success', 'Kode akses dibatalkan. Kata sandi tidak berubah.');
    }

    /**
     * Update admin password — wajib kode verifikasi email.
     *
     * Urutan sengaja: cek password lama dulu (kesalahan ketik umum tidak
     * menghabiskan percobaan kode), baru verifikasi kode, baru simpan.
     */
    public function updatePassword(Request $request, OtpService $otp)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'code' => ['required', 'array', 'size:6'],
            'code.*' => ['required', 'digits:1'],
        ], [
            'code.required' => 'Masukkan 6 digit kode verifikasi dari email Anda.',
            'code.size' => 'Kode verifikasi terdiri dari 6 digit.',
            'code.*.digits' => 'Setiap kotak hanya boleh berisi satu angka.',
        ]);

        $admin = Auth::user();

        if (!Hash::check($request->input('current_password'), $admin->password)) {
            return back()->withErrors(['current_password' => __('auth.password_incorrect')]);
        }

        $email = $otp->normalize($admin->email);
        $result = $otp->verify(
            $email,
            OtpService::PURPOSE_ADMIN_PASSWORD,
            implode('', $request->input('code')),
            'Kode akses admin'
        );

        if (!$result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $admin->update(['password' => Hash::make($request->input('new_password'))]);
        $otp->consume($email, OtpService::PURPOSE_ADMIN_PASSWORD);
        $request->session()->forget(self::PASSWORD_SENT_AT);
        $request->session()->regenerate();

        AuditLog::log(
            'admin.password.change',
            'Kata sandi akun admin diperbarui setelah verifikasi kode email.'
        );

        return back()->with('success', 'Kata sandi diperbarui. Gunakan kata sandi baru pada login berikutnya.');
    }
}