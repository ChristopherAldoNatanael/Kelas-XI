<?php

namespace App\Http\Controllers\Doctor;

use App\Models\AuditLog;
use App\Services\ImageService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Profil dokter: data yang memang milik dokter (nama, telepon, foto) dan
 * kata sandi akun. Spesialisasi, jadwal mingguan, serta email tetap dikelola
 * admin klinik agar identitas praktik tidak berubah sepihak.
 *
 * Ganti kata sandi TIDAK meminta kata sandi lama — dokter yang benar-benar
 * lupa sandinya tetap bisa menggantinya. Sebagai gantinya, perubahan wajib
 * diverifikasi kode 6 digit yang dikirim ke email akun yang sedang login.
 */
class DoctorProfileController extends DoctorPortalController
{
    /** Hash kata sandi baru yang menunggu verifikasi kode (satu sesi). */
    private const PASSWORD_PENDING = 'doctor_password_pending';

    /** Waktu kirim terakhir, untuk hitung mundur tombol kirim ulang. */
    private const PASSWORD_SENT_AT = 'doctor_password_otp_sent_at';

    public function edit(Request $request, OtpService $otp)
    {
        $doctor = $this->doctor();
        $user = $doctor->user;

        return view('doctor.profile.edit', [
            'doctor' => $doctor,
            'user' => $user,
            'passwordOtp' => $this->pendingPasswordOtp($request, $otp, $user),
        ]);
    }

    public function update(Request $request)
    {
        $doctor = $this->doctor();
        $user = $doctor->user;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $fileName = time() . '_' . uniqid() . '.jpg';
            $photoPath = ImageService::process($request->file('photo'), 'doctors/' . $fileName, 800, 80);
        }

        DB::transaction(function () use ($data, $photoPath, $doctor, $user) {
            $user->update(['name' => $data['name']]);

            $payload = [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ];

            if ($photoPath) {
                if ($doctor->photo) {
                    Storage::disk('public')->delete($doctor->photo);
                }

                $payload['photo'] = $photoPath;
            }

            $doctor->update($payload);
        });

        AuditLog::log('doctor.profile.update', "Dokter {$doctor->name} memperbarui profilnya.", $doctor);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * Langkah 1: simpan kata sandi baru (sebagai hash) lalu kirim kode
     * verifikasi ke email akun. Kata sandi BELUM berubah di tahap ini.
     *
     * Endpoint yang sama dipakai untuk "kirim ulang": bila sesi masih punya
     * kata sandi yang menunggu, kolom sandi tidak perlu diisi lagi.
     */
    public function sendPasswordOtp(Request $request, OtpService $otp)
    {
        $doctor = $this->doctor();
        $user = $doctor->user;
        $pending = $this->validPendingPassword($request, $user);

        if ($pending) {
            $hash = $pending['hash'];
        } else {
            $data = $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ], [
                'password.required' => 'Masukkan kata sandi baru.',
                'password.min' => 'Kata sandi baru minimal 8 karakter.',
                'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            ]);

            $hash = Hash::make($data['password']);
        }

        $email = $otp->normalize($user->email);

        $code = $otp->issue($email, OtpService::PURPOSE_DOCTOR_PASSWORD, [
            'user_id' => $user->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
        ]);

        $sent = $otp->send($email, OtpService::PURPOSE_DOCTOR_PASSWORD, $code, [
            'clinic_name' => $doctor->clinic?->name ?? 'PetHeal',
            'recipient_name' => $doctor->name,
        ]);

        if (!$sent) {
            return back()->with('error', 'Kode verifikasi gagal dikirim ke email. Coba lagi beberapa saat lagi.');
        }

        $ttl = $otp->ttlMinutes(OtpService::PURPOSE_DOCTOR_PASSWORD);

        $request->session()->put(self::PASSWORD_PENDING, [
            'user_id' => $user->id,
            'email' => $email,
            'hash' => $hash,
            'expires_at' => now()->addMinutes($ttl)->timestamp,
        ]);
        $request->session()->put(self::PASSWORD_SENT_AT, now()->timestamp);

        return back()->with('success', "Kode verifikasi dikirim ke {$email}. Berlaku {$ttl} menit.");
    }

    /**
     * Langkah 2: verifikasi kode lalu simpan hash kata sandi yang sudah
     * disiapkan. Tidak ada kolom kata sandi lama — kode email adalah bukti
     * bahwa yang mengganti memang pemilik akun.
     */
    public function updatePassword(Request $request, OtpService $otp)
    {
        $request->validate([
            'code' => ['required', 'array', 'size:6'],
            'code.*' => ['required', 'digits:1'],
        ], [
            'code.required' => 'Masukkan 6 digit kode verifikasi.',
            'code.size' => 'Kode verifikasi terdiri dari 6 digit.',
            'code.*.digits' => 'Kode verifikasi hanya berisi angka.',
        ]);

        $user = $this->doctor()->user;
        $pending = $this->validPendingPassword($request, $user);

        if (!$pending) {
            $this->forgetPasswordState($request);

            return back()->withErrors(['code' => 'Sesi verifikasi sudah berakhir. Minta kode baru.']);
        }

        $email = $otp->normalize($user->email);
        $result = $otp->verify(
            $email,
            OtpService::PURPOSE_DOCTOR_PASSWORD,
            implode('', $request->input('code')),
            'Kode verifikasi'
        );

        if (!$result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $user->update(['password' => $pending['hash']]);

        $otp->consume($email, OtpService::PURPOSE_DOCTOR_PASSWORD);
        $this->forgetPasswordState($request);
        $request->session()->regenerate();

        AuditLog::log('doctor.password.change', 'Dokter mengganti kata sandi portal setelah verifikasi kode email.', $user);

        return back()->with('success', 'Kata sandi berhasil diperbarui. Gunakan kata sandi baru saat login berikutnya.');
    }

    /** Batalkan verifikasi yang belum selesai; kata sandi tidak diubah. */
    public function cancelPasswordOtp(Request $request, OtpService $otp)
    {
        $user = $this->doctor()->user;

        $otp->consume($otp->normalize($user->email), OtpService::PURPOSE_DOCTOR_PASSWORD);
        $this->forgetPasswordState($request);

        return back()->with('success', 'Verifikasi dibatalkan. Kata sandi tidak diubah.');
    }

    /**
     * Data kode aktif untuk tampilan: null berarti masih di langkah 1.
     *
     * Sesi yang kedaluwarsa dibersihkan di sini supaya halaman tidak pernah
     * menampilkan kolom kode tanpa kata sandi baru yang menunggu.
     */
    private function pendingPasswordOtp(Request $request, OtpService $otp, $user): ?array
    {
        if (!$this->validPendingPassword($request, $user)) {
            $this->forgetPasswordState($request);

            return null;
        }

        $expiresAt = $otp->activeUntil(
            $otp->normalize($user->email),
            OtpService::PURPOSE_DOCTOR_PASSWORD
        );

        if (!$expiresAt) {
            $this->forgetPasswordState($request);

            return null;
        }

        $sentAt = (int) $request->session()->get(self::PASSWORD_SENT_AT, 0);

        return [
            'expires_at' => $expiresAt,
            'ttl' => $otp->ttlMinutes(OtpService::PURPOSE_DOCTOR_PASSWORD),
            'resend_wait' => max(0, 60 - (now()->timestamp - $sentAt)),
        ];
    }

    /**
     * Kata sandi yang menunggu verifikasi, hanya jika milik akun ini dan
     * belum kedaluwarsa. Hash disimpan (bukan plaintext) dan dibuang setelah
     * dipakai, sehingga sesi tidak pernah memuat sandi asli.
     */
    private function validPendingPassword(Request $request, $user): ?array
    {
        $pending = $request->session()->get(self::PASSWORD_PENDING);

        if (!is_array($pending)
            || (int) ($pending['user_id'] ?? 0) !== (int) $user->id
            || !isset($pending['hash'], $pending['expires_at'])
            || now()->timestamp > (int) $pending['expires_at']) {
            return null;
        }

        return $pending;
    }

    private function forgetPasswordState(Request $request): void
    {
        $request->session()->forget([self::PASSWORD_PENDING, self::PASSWORD_SENT_AT]);
    }
}
