<?php

namespace App\Mail;

use App\Services\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu mailable untuk semua kode verifikasi.
 *
 * Dua keluarga teks yang sengaja dibedakan:
 * - aplikasi pet owner: "kode OTP" + tindakan di aplikasi;
 * - dashboard admin: "kode akses admin" + tindakan di browser, dengan
 *   template email sendiri (emails.otp-admin) supaya penerima tidak
 *   tertukar antara kode pemilik hewan dan kode pengelola klinik.
 *
 * Tanpa gambar remote dan tanpa tautan: dua hal yang paling sering membuat
 * email verifikasi jatuh ke folder Spam.
 */
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose,
        public int $ttlMinutes,
        public ?string $clinicName = null,
        public ?string $recipientName = null,
    ) {
    }

    public function build()
    {
        if (OtpService::isAdminPurpose($this->purpose)) {
            return $this->buildAdminMail();
        }

        $isRegister = $this->purpose === OtpService::PURPOSE_REGISTER;

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

    private function buildAdminMail()
    {
        $isRegister = $this->purpose === OtpService::PURPOSE_ADMIN_REGISTER;
        $clinic = $this->clinicName ?: 'PetHeal';
        $greeting = trim((string) $this->recipientName) !== ''
            ? 'Halo ' . $this->recipientName . ','
            : 'Halo,';

        $subject = $isRegister
            ? "Kode akses admin — {$clinic}"
            : "Verifikasi kata sandi admin — {$clinic}";

        return $this
            ->subject($subject)
            ->view('emails.otp-admin')
            ->text('emails.otp-admin-text')
            ->with([
                'isRegister' => $isRegister,
                'clinicName' => $clinic,
                'greeting' => $greeting,
                'eyebrow' => $isRegister ? 'Pendaftaran admin klinik' : 'Keamanan akun admin',
                'title' => $isRegister ? 'Verifikasi email admin' : 'Kode ganti kata sandi',
                'intro' => $isRegister
                    ? "Kami menerima permintaan akses admin untuk {$clinic}. Masukkan kode akses di halaman pendaftaran admin untuk meneruskan permintaan ke super admin."
                    : "Kode ini diperlukan untuk mengganti kata sandi dashboard admin {$clinic}. Masukkan di halaman Pengaturan → Keamanan.",
                'outcome' => $isRegister
                    ? 'Permintaan Anda belum dikirim ke super admin sampai kode ini diverifikasi.'
                    : 'Kata sandi baru hanya tersimpan setelah kode ini diverifikasi.',
                'reason' => $isRegister ? 'pendaftaran admin klinik' : 'penggantian kata sandi admin',
            ]);
    }
}
