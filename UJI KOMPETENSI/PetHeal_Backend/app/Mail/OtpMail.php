<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu template email untuk semua kode OTP (pendaftaran & reset kata sandi),
 * isinya menyesuaikan $purpose.
 *
 * Sengaja tanpa gambar dari internet, tanpa tautan, dan kode ditulis sebagai
 * teks — tiga hal yang paling sering membuat email OTP masuk folder Spam.
 */
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
        public int $ttlMinutes,
    ) {
    }

    public function build()
    {
        $isRegister = $this->purpose === 'register';

        return $this
            ->subject($isRegister
                ? 'Kode OTP pendaftaran PetHeal'
                : 'Kode OTP reset kata sandi PetHeal')
            ->view('emails.otp')
            ->text('emails.otp-text')
            ->with([
                'title' => $isRegister
                    ? 'Verifikasi email pendaftaran'
                    : 'Reset kata sandi',
                'intro' => $isRegister
                    ? 'Masukkan kode ini di aplikasi untuk menyelesaikan pendaftaran akun PetHeal Anda.'
                    : 'Masukkan kode ini di aplikasi untuk mengatur ulang kata sandi akun PetHeal Anda.',
                'reason' => $isRegister ? 'pendaftaran akun' : 'reset kata sandi',
            ]);
    }
}
