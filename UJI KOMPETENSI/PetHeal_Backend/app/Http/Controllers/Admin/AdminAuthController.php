<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClinicRegistrationApprovedMail;
use App\Mail\ClinicRegistrationRejectedMail;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /** Email yang sedang menunggu kode verifikasi pendaftaran admin. */
    private const JOIN_EMAIL = 'admin_join_email';
    private const JOIN_SENT_AT = 'admin_join_otp_sent_at';

    /** Email yang permintaan klinik barunya sudah terkirim (halaman status). */
    private const JOIN_SUBMITTED = 'admin_join_submitted_email';

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

        if (Auth::check() && Auth::user()->role === 'doctor' && Auth::user()->doctor) {
            return redirect()->route('doctor.dashboard');
        }

        return view('admin.auth.login', ['portal' => 'admin']);
    }

    /**
     * Process login — supports super_admin, clinic_admin, legacy admin and
     * dokter (akun dokter diarahkan ke portal /doctor, bukan dasbor admin).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'remember' => 'nullable|boolean',
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])
            ->whereIn('role', ['super_admin', 'clinic_admin', 'admin', 'doctor'])
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

            // Akun dokter tanpa profil praktik tidak bisa memakai portal.
            if ($user->role === 'doctor' && !$user->doctor) {
                return back()->withErrors([
                    'email' => 'Akun dokter belum terhubung ke profil praktik. Hubungi admin klinik Anda.',
                ])->onlyInput('email');
            }

            // PHASE 4: wire the remember-me checkbox (was dead UI — the
            // credential cookie was never set).
            Auth::login($user, (bool) $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(
                $user->role === 'doctor'
                    ? route('doctor.dashboard')
                    : route('admin.dashboard')
            );
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
        $submitted = $pending ? null : $this->submittedRegistration($request);
        $mode = in_array($request->query('mode'), [ClinicJoinRequest::TYPE_JOIN, ClinicJoinRequest::TYPE_NEW_CLINIC], true)
            ? (string) $request->query('mode')
            : ClinicJoinRequest::TYPE_JOIN;

        return view('admin.auth.join-request', compact('clinics', 'pending', 'submitted', 'mode'));
    }

    /**
     * Langkah 1 pendaftaran admin: validasi data, buat kode, kirim ke email.
     * Belum ada ClinicJoinRequest yang dibuat di titik ini.
     *
     * Dua mode dalam satu form:
     * - join       : bergabung ke klinik yang sudah ada (alur lama);
     * - new_clinic : pemilik mendaftarkan klinik baru (dibuat saat disetujui).
     */
    public function requestJoinOtp(Request $request, OtpService $otp)
    {
        $type = $request->input('registration_type') === ClinicJoinRequest::TYPE_NEW_CLINIC
            ? ClinicJoinRequest::TYPE_NEW_CLINIC
            : ClinicJoinRequest::TYPE_JOIN;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|confirmed',
        ];

        if ($type === ClinicJoinRequest::TYPE_NEW_CLINIC) {
            $rules += [
                'clinic_name' => 'required|string|max:255',
                'clinic_slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'clinic_address' => 'required|string|max:1000',
                'clinic_phone' => 'required|string|max:50',
                'clinic_email' => 'nullable|email|max:255',
                'clinic_primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
                'clinic_description' => 'nullable|string|max:2000',
            ];
        } else {
            $rules['clinic_id'] = 'required|exists:clinics,id';
        }

        $validated = $request->validate($rules);
        $email = $otp->normalize($validated['email']);

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => __('auth.email_taken')])->withInput();
        }

        $alreadyPending = ClinicJoinRequest::where('email', $email)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return back()->withErrors([
                'email' => __('clinics.email_pending'),
            ])->withInput();
        }

        $clinic = null;
        $clinicPayload = null;

        if ($type === ClinicJoinRequest::TYPE_NEW_CLINIC) {
            $slug = $validated['clinic_slug'];

            if (Clinic::where('slug', $slug)->exists()
                || ClinicJoinRequest::newClinic()->where('requested_clinic_slug', $slug)->where('status', 'pending')->exists()) {
                return back()->withErrors(['clinic_slug' => __('clinics.slug_taken')])->withInput();
            }

            if ($this->newClinicAlreadyExists($validated['clinic_name'], $validated['clinic_phone'])) {
                return back()->withErrors(['clinic_name' => __('clinics.duplicate_clinic')])->withInput();
            }

            $clinicPayload = [
                'name' => $validated['clinic_name'],
                'slug' => $slug,
                'address' => $validated['clinic_address'],
                'phone' => $validated['clinic_phone'],
                'email' => $validated['clinic_email'] ?? null,
                'primary_color' => strtoupper($validated['clinic_primary_color']),
                'description' => $validated['clinic_description'] ?? null,
            ];
        } else {
            $clinic = Clinic::where('is_active', true)->find($validated['clinic_id']);
            if (!$clinic) {
                return back()->withErrors([
                    'clinic_id' => 'Klinik ini sedang tidak aktif. Pilih klinik lain atau hubungi super admin.',
                ])->withInput();
            }
        }

        $payload = [
            'type' => $type,
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'clinic_id' => $clinic?->id,
            'clinic' => $clinicPayload,
            // Password tidak pernah disimpan plaintext, termasuk di payload kode.
            'password_hash' => Hash::make($validated['password']),
        ];

        $code = $otp->issue($email, OtpService::PURPOSE_ADMIN_REGISTER, $payload);
        $sent = $otp->send($email, OtpService::PURPOSE_ADMIN_REGISTER, $code, [
            'clinic_name' => $clinic?->name ?? $clinicPayload['name'],
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
        $type = $payload['type'] ?? ClinicJoinRequest::TYPE_JOIN;
        $clinicPayload = $payload['clinic'] ?? null;

        if (empty($payload['email']) || empty($payload['password_hash'])
            || ($type === ClinicJoinRequest::TYPE_NEW_CLINIC
                ? (empty($clinicPayload['name']) || empty($clinicPayload['slug']) || empty($clinicPayload['address']))
                : empty($payload['clinic_id']))) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.register')
                ->with('warning', 'Data pendaftaran tidak ditemukan. Silakan isi formulir lagi.');
        }

        if ($type === ClinicJoinRequest::TYPE_NEW_CLINIC) {
            return $this->verifyNewClinicOtp($request, $otp, $email, $payload, $clinicPayload);
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
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.login')
                ->with('success', 'Email sudah terverifikasi dan permintaan Anda masih menunggu persetujuan super admin.');
        }

        ClinicJoinRequest::create([
            'type' => ClinicJoinRequest::TYPE_JOIN,
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
     * Verifikasi kode untuk pendaftaran klinik baru, lalu simpan permintaan.
     * Belum ada Clinic/User yang dibuat — itu terjadi saat super admin approve.
     */
    private function verifyNewClinicOtp(Request $request, OtpService $otp, string $email, array $payload, array $clinicPayload)
    {
        $fail = function (string $sessionKey, string $message) use ($request, $otp, $email) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);

            return redirect()->route('admin.register', ['mode' => ClinicJoinRequest::TYPE_NEW_CLINIC])
                ->withErrors([$sessionKey => $message]);
        };

        // Guardrail diulang setelah kode terbit: data bisa berubah di sela-sela.
        if (User::where('email', $email)->exists()) {
            return $fail('email', __('auth.email_taken'));
        }

        $alreadyPending = ClinicJoinRequest::where('email', $email)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
            $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);
            $request->session()->put(self::JOIN_SUBMITTED, $email);

            return redirect()->route('admin.register')
                ->with('success', __('clinics.email_pending'));
        }

        $slug = $clinicPayload['slug'];
        if (Clinic::where('slug', $slug)->exists()
            || ClinicJoinRequest::newClinic()->where('requested_clinic_slug', $slug)->where('status', 'pending')->exists()) {
            return $fail('clinic_slug', __('clinics.slug_taken'));
        }

        if ($this->newClinicAlreadyExists($clinicPayload['name'], $clinicPayload['phone'])) {
            return $fail('clinic_name', __('clinics.duplicate_clinic'));
        }

        ClinicJoinRequest::create([
            'type' => ClinicJoinRequest::TYPE_NEW_CLINIC,
            // Klinik belum ada; clinic_id diisi saat approve.
            'clinic_id' => null,
            'name' => $payload['name'] ?? '',
            'email' => $email,
            'phone' => $payload['phone'] ?? null,
            'password_hash' => $payload['password_hash'],
            'status' => 'pending',
            'requested_clinic_name' => $clinicPayload['name'],
            'requested_clinic_slug' => $slug,
            'requested_clinic_address' => $clinicPayload['address'],
            'requested_clinic_phone' => $clinicPayload['phone'],
            'requested_clinic_email' => $clinicPayload['email'] ?? null,
            'requested_primary_color' => $clinicPayload['primary_color'] ?? '#18C964',
            'requested_description' => $clinicPayload['description'] ?? null,
        ]);

        $otp->consume($email, OtpService::PURPOSE_ADMIN_REGISTER);
        $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT]);
        $request->session()->put(self::JOIN_SUBMITTED, $email);
        $request->session()->regenerate();

        return redirect()->route('admin.register')->with(
            'success',
            'Email ' . $email . ' terverifikasi. Permintaan pendaftaran klinik '
                . $clinicPayload['name'] . ' sudah dikirim ke super admin.'
        );
    }

    /** Klinik dengan nama + telepon yang sama sudah ada / sedang menunggu. */
    private function newClinicAlreadyExists(string $name, ?string $phone): bool
    {
        return Clinic::where('name', $name)->where('phone', $phone)->exists()
            || ClinicJoinRequest::newClinic()
                ->where('requested_clinic_name', $name)
                ->where('requested_clinic_phone', $phone)
                ->where('status', 'pending')
                ->exists();
    }

    /**
     * Status permintaan klinik baru setelah email diverifikasi. Ditampilkan di
     * halaman yang sama (/admin/register) supaya tidak ada halaman status kedua.
     *
     * @return array<string, mixed>|null
     */
    private function submittedRegistration(Request $request): ?array
    {
        $email = (string) $request->session()->get(self::JOIN_SUBMITTED, '');

        if ($email === '') {
            return null;
        }

        $joinRequest = ClinicJoinRequest::with('clinic')
            ->where('email', $email)
            ->latest('id')
            ->first();

        if (!$joinRequest) {
            $request->session()->forget(self::JOIN_SUBMITTED);

            return null;
        }

        return [
            'email' => $email,
            'name' => $joinRequest->name,
            'clinic_name' => $joinRequest->displayClinicName(),
            'clinic_color' => $joinRequest->displayClinicColor(),
            'status' => $joinRequest->status,
            'review_note' => $joinRequest->review_note,
            'is_new_clinic' => $joinRequest->isNewClinic(),
            'submitted_at' => $joinRequest->created_at,
        ];
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
            'clinic_name' => $clinic?->name ?? ($payload['clinic']['name'] ?? 'PetHeal'),
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

        $request->session()->forget([self::JOIN_EMAIL, self::JOIN_SENT_AT, self::JOIN_SUBMITTED]);

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
            'type' => $payload['type'] ?? ClinicJoinRequest::TYPE_JOIN,
            'email' => $email,
            'name' => $payload['name'] ?? null,
            'clinic_name' => $clinic?->name ?? ($payload['clinic']['name'] ?? 'PetHeal'),
            'clinic_color' => $clinic?->primary_color ?? ($payload['clinic']['primary_color'] ?? '#18C964'),
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
     * Daftar permintaan untuk super admin.
     *
     * Sengaja TIDAK difilter currentClinicId: permintaan klinik baru punya
     * clinic_id = null dan akan hilang kalau super admin sedang berada di
     * konteks salah satu klinik. Route ini sudah dibatasi middleware
     * super_admin, jadi seluruh permintaan memang milik super admin.
     */
    public function joinRequests(Request $request)
    {
        $type = (string) $request->query('type', 'all');

        $requests = ClinicJoinRequest::with(['clinic', 'reviewedBy'])
            ->when(
                in_array($type, [ClinicJoinRequest::TYPE_JOIN, ClinicJoinRequest::TYPE_NEW_CLINIC], true),
                fn ($q) => $q->where('type', $type)
            )
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all' => ClinicJoinRequest::count(),
            ClinicJoinRequest::TYPE_JOIN => ClinicJoinRequest::where('type', ClinicJoinRequest::TYPE_JOIN)->count(),
            ClinicJoinRequest::TYPE_NEW_CLINIC => ClinicJoinRequest::where('type', ClinicJoinRequest::TYPE_NEW_CLINIC)->count(),
            'pending' => ClinicJoinRequest::where('status', 'pending')->count(),
        ];

        return view('admin.join-requests', compact('requests', 'type', 'counts'));
    }

    /**
     * Approve a pending request.
     *
     * `join`       : hanya membuat akun clinic_admin untuk klinik yang ada.
     * `new_clinic` : membuat Clinic + akun clinic_admin + audit log dalam satu
     *                transaksi. Slug/email dicek ulang di dalam lock supaya
     *                approval gagal dengan pesan jelas, bukan 500.
     */
    public function approveJoinRequest(Request $request, $id)
    {
        $wasRejected = false;

        // PHASE 5 (SA-02): approve + user create must be atomic under a row
        // lock — concurrent double-approvals previously raced past the
        // pending check (one side then hit users.email unique as a 500).
        $joinRequest = DB::transaction(function () use ($id, &$wasRejected) {
            $joinRequest = ClinicJoinRequest::where('status', 'pending')
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($joinRequest->isNewClinic()) {
                $slug = (string) $joinRequest->requested_clinic_slug;

                $slugTaken = $slug === ''
                    || Clinic::where('slug', $slug)->exists()
                    || ClinicJoinRequest::newClinic()
                        ->where('requested_clinic_slug', $slug)
                        ->where('status', 'pending')
                        ->where('id', '<>', $joinRequest->id)
                        ->exists();

                if ($slugTaken) {
                    throw ValidationException::withMessages([
                        'slug' => __('clinics.slug_taken_on_approve', ['slug' => $slug !== '' ? $slug : '—']),
                    ]);
                }

                if (User::where('email', $joinRequest->email)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => __('clinics.email_taken_on_approve', ['email' => $joinRequest->email]),
                    ]);
                }

                $clinic = Clinic::create([
                    'name' => $joinRequest->requested_clinic_name,
                    'slug' => $slug,
                    'address' => $joinRequest->requested_clinic_address,
                    'phone' => $joinRequest->requested_clinic_phone,
                    'email' => $joinRequest->requested_clinic_email,
                    'primary_color' => $joinRequest->requested_primary_color ?: '#18C964',
                    'description' => $joinRequest->requested_description,
                    'is_active' => true,
                ]);

                User::create([
                    'name' => $joinRequest->name,
                    'email' => $joinRequest->email,
                    'phone' => $joinRequest->phone,
                    'password' => $joinRequest->password_hash,
                    'role' => 'clinic_admin',
                    'clinic_id' => $clinic->id,
                    'email_verified_at' => now(),
                ]);

                $joinRequest->update([
                    'status' => 'approved',
                    'clinic_id' => $clinic->id,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);

                // clinic_id ditulis eksplisit: super admin biasanya tidak punya
                // konteks klinik, jadi AuditLog::log() akan menyimpan null.
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'clinic_id' => $clinic->id,
                    'action' => 'clinic.registration.approved',
                    'model_type' => Clinic::class,
                    'model_id' => $clinic->id,
                    'description' => 'Klinik baru ' . $clinic->name . ' (' . $clinic->slug . ') disetujui untuk ' . $joinRequest->email,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $joinRequest->fresh(['clinic']);
            }

            // Check if email already taken by any user
            if (User::where('email', $joinRequest->email)->exists()) {
                // Auto-reject this request since email is taken
                $joinRequest->update([
                    'status' => 'rejected',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'review_note' => __('clinics.email_already_used'),
                ]);
                $wasRejected = true;

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

        if ($wasRejected || $joinRequest->status === 'rejected') {
            return back()->withErrors(['email' => __('clinics.email_already_used')]);
        }

        if ($joinRequest->isNewClinic()) {
            $this->sendClinicDecisionMail($joinRequest, approved: true);

            return back()->with('success', __('clinics.new_clinic_approved_success', [
                'name' => $joinRequest->displayClinicName(),
            ]));
        }

        return back()->with('success', __('clinics.approved_success', ['name' => $joinRequest->name]));
    }

    /**
     * Reject a pending request. Klinik/akun tidak pernah dibuat di sini.
     */
    public function rejectJoinRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'review_note' => 'nullable|string|max:1000',
        ]);

        $joinRequest = ClinicJoinRequest::where('status', 'pending')->where('id', $id)->firstOrFail();

        $joinRequest->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        if ($joinRequest->isNewClinic()) {
            $this->sendClinicDecisionMail($joinRequest, approved: false);
        }

        return back()->with(
            'success',
            $joinRequest->isNewClinic()
                ? __('clinics.new_clinic_rejected_success', ['name' => $joinRequest->displayClinicName()])
                : __('clinics.rejected_success', ['name' => $joinRequest->name])
        );
    }

    /**
     * Email keputusan hanya untuk pendaftaran klinik baru — alur "gabung
     * klinik" sengaja tidak berubah. Kegagalan kirim email tidak membatalkan
     * keputusan yang sudah tersimpan.
     */
    private function sendClinicDecisionMail(ClinicJoinRequest $joinRequest, bool $approved): void
    {
        try {
            $mailable = $approved
                ? new ClinicRegistrationApprovedMail(
                    clinicName: $joinRequest->displayClinicName(),
                    ownerName: $joinRequest->name,
                    loginUrl: route('admin.login'),
                )
                : new ClinicRegistrationRejectedMail(
                    clinicName: $joinRequest->displayClinicName(),
                    ownerName: $joinRequest->name,
                    reason: $joinRequest->review_note,
                );

            Mail::to($joinRequest->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Email keputusan pendaftaran klinik gagal dikirim', [
                'request_id' => $joinRequest->id,
                'status' => $approved ? 'approved' : 'rejected',
                'error' => $e->getMessage(),
            ]);
        }
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

        // One page, three tabs: account, security, and clinic. The clinic
        // tab is only available when a clinic context is active.
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

        return view('admin.settings', compact('clinic', 'activeTab', 'passwordOtp'));
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
                ? __('admin.password_otp_sent', [
                    'email' => $email,
                    'minutes' => $otp->ttlMinutes(OtpService::PURPOSE_ADMIN_PASSWORD),
                ])
                : __('admin.password_otp_failed')
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

        return back()->with('success', __('admin.password_otp_cancelled'));
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
            'code.required' => __('admin.password_code_required'),
            'code.size' => __('admin.password_code_size'),
            'code.*.digits' => __('admin.password_code_digit'),
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
            __('admin.password_code_label')
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

        return back()->with('success', __('admin.password_updated'));
    }
}