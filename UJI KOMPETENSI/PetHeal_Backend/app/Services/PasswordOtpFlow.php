<?php

namespace App\Services;

use App\Models\User;

/**
 * Satu jalur untuk "ganti kata sandi lewat kode email".
 *
 * Sebelumnya alur ini ditulis dua kali: portal admin
 * (`Admin\AdminAuthController::sendPasswordOtp`) dan portal dokter
 * (`Doctor\DoctorProfileController::sendPasswordOtp`). Isinya sama —
 * normalisasi email, terbitkan kode, kirim, verifikasi, dan habiskan kode —
 * hanya tujuannya yang berbeda (`OtpService::PURPOSE_*`). Duplikasi itu berarti
 * setiap perbaikan keamanan harus dikerjakan dua kali dan mudah tertinggal di
 * salah satu sisi (docs/AUDIT_FIXES_REPORT.md UIX-04).
 *
 * Service ini hanya membungkus mekanika OTP. Aturan yang memang berbeda per
 * portal tetap di controller masing-masing: admin memverifikasi kata sandi
 * lama lebih dulu, dokter tidak (kode email adalah bukti kepemilikan akun)
 * dan menyimpan hash kata sandi baru di sesi sebelum kode dikirim.
 */
class PasswordOtpFlow
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Terbitkan dan kirim kode untuk satu pengguna.
     *
     * @param  array<string,mixed>  $payload  konteks tambahan yang disimpan bersama kode
     * @return array{email:string,sent:bool,ttl:int}
     */
    public function start(User $user, string $purpose, array $payload, string $clinicName, string $recipientName): array
    {
        $email = $this->otp->normalize($user->email);

        $code = $this->otp->issue($email, $purpose, $payload);

        $sent = $this->otp->send($email, $purpose, $code, [
            'clinic_name' => $clinicName,
            'recipient_name' => $recipientName,
        ]);

        return [
            'email' => $email,
            'sent' => $sent,
            'ttl' => $this->otp->ttlMinutes($purpose),
        ];
    }

    /**
     * Verifikasi kode dan langsung habiskan bila benar.
     *
     * @param  array<int,string|int>|string  $code  deretan kotak digit atau string 6 digit
     * @return array{ok:bool,message:string}
     */
    public function verify(User $user, string $purpose, array|string $code, string $label): array
    {
        $digits = is_array($code) ? implode('', $code) : (string) $code;

        $result = $this->otp->verify(
            $this->otp->normalize($user->email),
            $purpose,
            $digits,
            $label
        );

        if (!empty($result['ok'])) {
            $this->otp->consume($this->otp->normalize($user->email), $purpose);
        }

        return $result;
    }

    /** Buang kode yang belum terpakai (pengguna menekan "Batalkan"). */
    public function cancel(User $user, string $purpose): void
    {
        $this->otp->consume($this->otp->normalize($user->email), $purpose);
    }

    public function ttlMinutes(string $purpose): int
    {
        return $this->otp->ttlMinutes($purpose);
    }

    public function normalize(string $email): string
    {
        return $this->otp->normalize($email);
    }
}
