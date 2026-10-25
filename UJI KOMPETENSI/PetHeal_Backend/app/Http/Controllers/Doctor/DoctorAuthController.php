<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Masuk & aktivasi akun portal dokter.
 *
 * Akun dokter tidak dibuat bebas: admin klinik mengundang email dokter lebih
 * dulu lewat tautan aktivasi bertoken (berlaku 60 menit, sekali pakai), lalu
 * dokter menentukan kata sandinya sendiri. Tidak ada kode OTP yang perlu
 * diketik ulang — kepemilikan email dibuktikan dengan membuka tautan tersebut.
 */
class DoctorAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            if (Auth::user()->role === 'doctor' && Auth::user()->doctor) {
                return redirect()->route('doctor.dashboard');
            }

            if (in_array(Auth::user()->role, ['super_admin', 'clinic_admin', 'admin'], true)) {
                return redirect()->route('admin.dashboard');
            }
        }

        return view('admin.auth.login', ['portal' => 'doctor']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'remember' => 'nullable|boolean',
        ]);

        $user = User::where('email', $credentials['email'])
            ->where('role', 'doctor')
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => __('auth.invalid_credentials'),
            ])->onlyInput('email');
        }

        $clinic = $user->clinic;

        if (!$user->clinic_id || !$clinic || !$clinic->is_active) {
            return back()->withErrors([
                'email' => __('auth.inactive_clinic'),
            ])->onlyInput('email');
        }

        if (!$user->doctor) {
            return back()->withErrors([
                'email' => 'Akun dokter belum terhubung ke profil praktik. Hubungi admin klinik Anda.',
            ])->onlyInput('email');
        }

        if ((int) $user->doctor->clinic_id !== (int) $user->clinic_id) {
            return back()->withErrors([
                'email' => 'Profil dokter tidak berada di klinik akun ini. Hubungi admin klinik Anda.',
            ])->onlyInput('email');
        }

        Auth::login($user, (bool) $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('doctor.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('doctor.login')
            ->with('success', 'Anda telah keluar dari portal dokter.');
    }

    public function showActivationNotice()
    {
        if (Auth::check() && Auth::user()->role === 'doctor' && Auth::user()->doctor) {
            return redirect()->route('doctor.dashboard');
        }

        return view('doctor.auth.activate-notice');
    }

    public function showActivationForm(string $token)
    {
        if (Auth::check() && Auth::user()->role === 'doctor' && Auth::user()->doctor) {
            return redirect()->route('doctor.dashboard');
        }

        $doctor = $this->findInvitedDoctor($token);

        if (!$doctor) {
            return redirect()->route('doctor.activate')
                ->withErrors(['email' => 'Tautan aktivasi tidak valid atau sudah kedaluwarsa. Minta admin klinik mengirim ulang undangan.']);
        }

        if (!$doctor->clinic || !$doctor->clinic->is_active) {
            return redirect()->route('doctor.activate')
                ->withErrors(['email' => __('auth.inactive_clinic')]);
        }

        return view('doctor.auth.activate', [
            'doctor' => $doctor,
            'token' => $token,
        ]);
    }

    /**
     * Aktivasi lewat tautan email: token acak (bukan kode OTP) + kata sandi.
     * Token hangus dalam transaksi yang sama supaya tidak bisa dipakai dua kali.
     */
    public function activate(Request $request, string $token)
    {
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Tentukan kata sandi portal dokter Anda.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        try {
            $user = DB::transaction(function () use ($token, $data) {
                $doctor = $this->findInvitedDoctor($token, true);

                if (!$doctor) {
                    throw new \RuntimeException('invalid_token');
                }

                if (!$doctor->clinic || !$doctor->clinic->is_active) {
                    throw new \RuntimeException('inactive_clinic');
                }

                $email = strtolower(trim((string) $doctor->email));

                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('email_missing');
                }

                if (User::where('email', $email)->exists()) {
                    throw new \RuntimeException('email_taken');
                }

                $user = User::create([
                    'name' => $doctor->name,
                    'email' => $email,
                    'phone' => $doctor->phone,
                    'password' => Hash::make($data['password']),
                    'role' => 'doctor',
                    'clinic_id' => $doctor->clinic_id,
                    'email_verified_at' => now(),
                ]);

                $doctor->forceFill([
                    'user_id' => $user->id,
                    'invite_token' => null,
                    'invite_token_expires_at' => null,
                ])->save();

                return $user;
            });
        } catch (\RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'inactive_clinic' => __('auth.inactive_clinic'),
                'email_missing' => 'Profil dokter belum memiliki email yang valid. Hubungi admin klinik Anda.',
                'email_taken' => 'Email ini sudah dipakai akun lain. Hubungi admin klinik untuk memakai alamat email berbeda.',
                default => 'Tautan aktivasi tidak valid atau sudah kedaluwarsa. Minta admin klinik mengirim ulang undangan.',
            };

            return redirect()->route('doctor.activate')->withErrors(['email' => $message]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::log(
            'doctor.account.activate',
            "Akun portal dokter diaktifkan untuk {$user->name} ({$user->email}).",
            $user->doctor
        );

        // Halaman sukses sekali tampil: memberi konfirmasi jelas sebelum
        // dokter masuk ke dashboard. Refresh langsung diarahkan ke dashboard.
        return redirect()->route('doctor.welcome')->with('doctor_activation', [
            'name' => $user->name,
            'email' => $user->email,
            'clinic' => $user->clinic?->name,
        ]);
    }

    /**
     * Layar "aktivasi berhasil" setelah kata sandi disimpan.
     *
     * Hanya bisa dibuka tepat setelah aktivasi (flash session sekali pakai);
     * akses langsung tanpa flash diarahkan ke dashboard agar tidak menjadi
     * halaman mati.
     */
    public function welcome(Request $request)
    {
        $activation = $request->session()->get('doctor_activation');

        if (!is_array($activation) || empty($activation['name'])) {
            return redirect()->route('doctor.dashboard');
        }

        return view('doctor.auth.welcome', [
            'name' => (string) $activation['name'],
            'email' => (string) ($activation['email'] ?? Auth::user()?->email ?? ''),
            'clinicName' => (string) ($activation['clinic'] ?? Auth::user()?->clinic?->name ?? 'PetHeal'),
        ]);
    }

    /**
     * Cari dokter yang masih punya tautan undangan aktif.
     *
     * Token di URL tidak pernah disimpan apa adanya; yang dicocokkan adalah
     * hash SHA-256 miliknya. `$lock` dipakai saat aktivasi agar dua submit
     * bersamaan tidak membuat dua akun.
     */
    private function findInvitedDoctor(string $token, bool $lock = false): ?Doctor
    {
        if ($token === '' || strlen($token) < 40) {
            return null;
        }

        $query = Doctor::with('clinic')
            ->where('invite_token', hash('sha256', $token))
            ->whereNull('user_id')
            ->whereNotNull('invite_token_expires_at')
            ->where('invite_token_expires_at', '>', now());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
