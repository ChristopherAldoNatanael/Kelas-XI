<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Jalur kirim KHUSUS email verifikasi (kode OTP + tautan aktivasi akun).
 *
 * Kenapa ada kelas ini
 * --------------------
 * Email verifikasi harus lewat penyedia transaksional (Brevo) supaya tidak
 * ditahan filter Gmail, sedangkan email lain (mis. hasil pendaftaran klinik)
 * tetap memakai mailer bawaan di MAIL_MAILER. Notifikasi push Firebase
 * TIDAK lewat jalur ini sama sekali — FCM punya layanan sendiri.
 *
 * Jebakan yang sudah dibayar mahal
 * --------------------------------
 * JANGAN memilih Brevo dengan `$mailable->mailer('brevo')`. Laravel menimpa
 * properti itu di Illuminate\Mail\Mailer::sendMailable() dengan mailer yang
 * sedang aktif, sehingga email tetap keluar lewat mailer bawaan tanpa error
 * apa pun. Jalur harus dipilih saat mengirim: `Mail::mailer('brevo')`.
 *
 * Aturan aman
 * -----------
 * Kalau kredensial Brevo belum diisi di .env, semua kembali memakai mailer
 * bawaan. Jadi aplikasi tidak pernah gagal kirim hanya karena setup Brevo
 * belum lengkap, dan pengujian lokal tidak butuh akun Brevo.
 */
final class VerificationMailer
{
    /** Relay SMTP Brevo di config/mail.php. */
    private const BREVO_SMTP = 'brevo';

    /** REST API Brevo di config/mail.php (tanpa autentikasi SMTP). */
    private const BREVO_API = 'brevo-api';

    /** Satu percobaan ulang untuk gangguan SMTP sesaat. */
    private const MAX_ATTEMPTS = 2;

    /** Pesan kegagalan terakhir — untuk ditampilkan alat diagnostik. */
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Jalur Brevo siap dipakai: API lebih diutamakan daripada SMTP.
     *
     * API hanya butuh BREVO_API_KEY, sehingga email tetap terkirim walau
     * login/key SMTP salah — kegagalan yang paling sering terjadi.
     */
    public static function configured(): bool
    {
        return self::apiConfigured() || self::smtpConfigured();
    }

    public static function apiConfigured(): bool
    {
        return filled(config('services.brevo.key'));
    }

    public static function smtpConfigured(): bool
    {
        return filled(config('mail.mailers.brevo.username'))
            && filled(config('mail.mailers.brevo.password'));
    }

    /**
     * Nama mailer yang dipakai email verifikasi saat ini.
     */
    public static function name(): string
    {
        if (self::apiConfigured()) {
            return self::BREVO_API;
        }

        if (self::smtpConfigured()) {
            return self::BREVO_SMTP;
        }

        return (string) config('mail.default');
    }

    /**
     * Pasang alamat pengirim yang sudah diverifikasi di Brevo.
     *
     * Alamat dipaksa ke VERIFICATION_MAIL_FROM_ADDRESS karena Brevo menolak
     * pengirim yang belum terdaftar di akunnya. Kalau kosong, mailable dibiarkan
     * memakai MAIL_FROM_ADDRESS.
     */
    public static function applyTo(Mailable $mailable): Mailable
    {
        $address = trim((string) config('mail.verification.from.address'));

        if ($address !== '') {
            $mailable->from($address, (string) config('mail.verification.from.name'));
        }

        return $mailable;
    }

    /**
     * Kirim email verifikasi lewat jalur yang benar, dengan satu percobaan ulang.
     *
     * Nilai balik sengaja bool: hasil kirim hanya dianggap sukses bila tidak ada
     * exception. (MailFake mengembalikan null, jadi objek SentMessage tidak bisa
     * dipakai sebagai penanda berhasil.)
     *
     * @param  array<string, mixed>  $context  Ikut dicatat di log (purpose, clinic, dll).
     */
    public static function send(string $email, Mailable $mailable, array $context = []): bool
    {
        $mailer = self::name();
        self::$lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $sent = Mail::mailer($mailer)->to($email)->send($mailable);

                // Message-ID dicatat supaya kejadian di dashboard Brevo
                // (Transactional > Logs) bisa dicocokkan dengan kiriman ini.
                Log::info('Email verifikasi terkirim', $context + [
                    'email' => $email,
                    'mailer' => $mailer,
                    'message_id' => $sent?->getMessageId(),
                ]);

                return true;
            } catch (Throwable $e) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    continue;
                }

                self::$lastError = $e->getMessage();

                Log::warning('Email verifikasi gagal dikirim', $context + [
                    'email' => $email,
                    'mailer' => $mailer,
                    'percobaan' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        return false;
    }
}
