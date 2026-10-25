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
        if (OtpService::isDashboardPurpose($this->purpose)) {
            return $this->buildAdminMail();
        }

        return $this->buildMemberMail();
    }

    /**
     * Email untuk aksi sensitif di aplikasi pet owner. Satu template, teks
     * dibedakan per tujuan supaya subjek tidak salah (dulu semua tujuan
     * non-register tampil sebagai "reset kata sandi").
     */
    private function buildMemberMail()
    {
        $config = match ($this->purpose) {
            OtpService::PURPOSE_REGISTER => [
                'subject' => 'Kode OTP pendaftaran PetHeal',
                'title' => 'Verifikasi email pendaftaran',
                'intro' => 'Masukkan kode ini di aplikasi untuk menyelesaikan pendaftaran akun PetHeal Anda.',
                'reason' => 'pendaftaran akun',
            ],
            OtpService::PURPOSE_DELETE_ACCOUNT => [
                'subject' => 'Kode konfirmasi hapus akun PetHeal',
                'title' => 'Konfirmasi hapus akun',
                'intro' => 'Masukkan kode ini di aplikasi untuk mengonfirmasi penghapusan akun PetHeal Anda secara permanen.',
                'reason' => 'penghapusan akun',
            ],
            OtpService::PURPOSE_EMAIL_CHANGE => [
                'subject' => 'Kode konfirmasi email baru PetHeal',
                'title' => 'Konfirmasi alamat email baru',
                'intro' => 'Masukkan kode ini di aplikasi untuk menyelesaikan perubahan alamat email akun PetHeal Anda.',
                'reason' => 'perubahan alamat email',
            ],
            default => [
                'subject' => 'Kode OTP reset kata sandi PetHeal',
                'title' => 'Reset kata sandi',
                'intro' => 'Masukkan kode ini di aplikasi untuk mengatur ulang kata sandi akun PetHeal Anda.',
                'reason' => 'reset kata sandi',
            ],
        };

        return $this
            ->subject($config['subject'])
            ->view('emails.otp')
            ->text('emails.otp-text')
            ->with([
                'title' => $config['title'],
                'intro' => $config['intro'],
                'reason' => $config['reason'],
            ]);
    }

    private function buildAdminMail()
    {
        $isRegister = $this->purpose === OtpService::PURPOSE_ADMIN_REGISTER;
        $isDoctor = $this->purpose === OtpService::PURPOSE_DOCTOR_PASSWORD;
        $clinic = $this->clinicName ?: 'PetHeal';
        $greeting = trim((string) $this->recipientName) !== ''
            ? 'Halo ' . $this->recipientName . ','
            : 'Halo,';

        if ($isDoctor) {
            return $this
                ->subject("Verifikasi kata sandi dokter — {$clinic}")
                ->view('emails.otp-admin')
                ->text('emails.otp-admin-text')
                ->with([
                    'isRegister' => false,
                    'clinicName' => $clinic,
                    'greeting' => $greeting,
                    'eyebrow' => 'Keamanan akun dokter',
                    'title' => 'Kode ganti kata sandi',
                    'intro' => "Kode ini diperlukan untuk mengganti kata sandi portal dokter {$clinic}. Masukkan di halaman Profil Saya → Ganti kata sandi.",
                    'outcome' => 'Kata sandi baru hanya tersimpan setelah kode ini diverifikasi.',
                    'reason' => 'penggantian kata sandi dokter',
                    // Label template dashboard diganti agar email tidak menyebut "admin".
                    'consoleLabel' => 'Portal Dokter',
                    'accessLabel' => 'Kode verifikasi',
                    'recipientRoleLabel' => 'dokter',
                    'footerLabel' => 'Portal Dokter PetHeal',
                    'securityNote' => 'Tim PetHeal tidak pernah meminta kode ini melalui telepon, WhatsApp, atau chat. Kode portal dokter tidak berlaku di aplikasi pet owner.',
                ]);
        }

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
