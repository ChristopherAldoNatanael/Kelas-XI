<?php

namespace App\Console\Commands;

use App\Mail\DoctorInviteMail;
use App\Mail\MailDeliveryCheck;
use App\Mail\OtpMail;
use App\Services\OtpService;
use App\Support\VerificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Cek cepat "apakah email dari server ini benar-benar keluar?".
 *
 * Dipakai saat user melaporkan kode OTP tidak masuk: kalau perintah ini
 * berhasil, masalahnya ada di folder Spam/karantina penerima atau di alamat
 * email yang salah ketik — bukan di SMTP, kredensial, atau template.
 *
 * Perintah ini tidak membuat kode OTP apa pun (lihat `petheal:otp-status`
 * untuk memeriksa kode yang sudah terbit).
 */
class TestMailDelivery extends Command
{
    protected $signature = 'petheal:mail-test
        {email : Alamat tujuan email uji}
        {--template=check : check|otp|doctor. Template yang diuji: email uji biasa, template kode OTP, atau template undangan dokter}
        {--subject= : Timpa subjek email (dipakai untuk uji A/B filter spam)}';

    protected $description = 'Kirim satu email uji untuk memastikan SMTP PetHeal benar-benar mengantar email';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Alamat email tidak valid: {$email}");

            return self::FAILURE;
        }

        $template = (string) $this->option('template');

        if (!in_array($template, ['check', 'otp', 'doctor'], true)) {
            $this->error("Template tidak dikenal: {$template}. Pilih check, otp, atau doctor.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');

        $this->line('Konfigurasi aktif:');
        $this->line('  mailer      : ' . $mailer);

        if ($mailer === 'smtp') {
            $this->line('  host        : ' . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'));
            $this->line('  scheme      : ' . (config('mail.mailers.smtp.scheme') ?: '(kosong = STARTTLS otomatis di port 587)'));
            $this->line('  username    : ' . $this->maskEmail((string) config('mail.mailers.smtp.username')));
            $this->line('  password    : ' . (config('mail.mailers.smtp.password') ? 'terisi' : 'KOSONG'));
        }

        $this->line('  pengirim    : ' . $fromAddress);
        $this->line('  APP_URL     : ' . config('app.url'));
        $this->line('  jalur verifikasi (OTP & tautan aktivasi): ' . $this->describeVerificationMailer());

        $verificationSender = (string) config('mail.verification.from.address');

        if ($this->isFreeMailDomain($verificationSender)) {
            $this->line('  catatan     : pengirim ' . $verificationSender
                . ' memakai domain gratis, jadi hanya bisa diverifikasi sebagai sender (tanpa DKIM domain).'
                . ' Untuk deliverability terbaik, pakai domain sendiri.');
        }
        $this->newLine();

        $this->line("Mengirim email uji ke {$email} ...");

        $subjectOverride = trim((string) $this->option('subject'));

        try {
            // Setiap mode sengaja memakai mailable asli milik alurnya. Kalau
            // hanya template "email uji" yang dicoba, template verifikasi bisa
            // tetap masuk Spam tanpa ketahuan.
            $mailable = match ($template) {
                'otp' => new OtpMail(
                    // Kode contoh: tidak pernah disimpan, tidak bisa dipakai masuk.
                    '000000',
                    OtpService::PURPOSE_REGISTER,
                    10,
                ),
                'doctor' => new DoctorInviteMail(
                    // Tautan contoh: token acak yang tidak ada di database,
                    // jadi halaman aktivasi akan menjawab "tautan tidak berlaku".
                    route('doctor.activate.token', ['token' => \Illuminate\Support\Str::random(64)]),
                    'Klinik Contoh PetHeal',
                    null,
                    60,
                ),
                default => new MailDeliveryCheck(
                    now()->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i') . ' WIB',
                    $mailer,
                    $fromAddress,
                ),
            };

            if ($subjectOverride !== '') {
                $mailable->subject($subjectOverride);
            }

            // Template verifikasi diuji lewat jalur produksinya (Brevo bila ada).
            // Template "check" tetap memakai MAIL_MAILER sebagai pembanding.
            if ($template === 'check') {
                Mail::to($email)->send($mailable);
            } elseif (!VerificationMailer::send($email, $mailable, ['tujuan' => 'uji_' . $template])) {
                throw new \RuntimeException(
                    'Jalur ' . VerificationMailer::name() . ' menolak pengiriman: '
                    . (VerificationMailer::lastError() ?? 'tanpa pesan dari penyedia')
                );
            }

            $this->line('Template diuji: ' . $template);
            $this->line('Jalur kirim  : ' . ($template === 'check' ? (string) config('mail.default') : VerificationMailer::name()));
            $this->line('Subjek        : ' . ($subjectOverride !== '' ? $subjectOverride : '(bawaan template)'));
        } catch (\Throwable $e) {
            $this->error('GAGAL: server email menolak pengiriman.');
            $this->line($e->getMessage());
            $this->newLine();
            $this->line('Petunjuk perbaikan:');
            $this->line('  - "Username and Password not accepted" (535) = kredensial SMTP salah/dicabut.');
            $this->line('    Gmail: App Password di https://myaccount.google.com/apppasswords (MAIL_PASSWORD).');
            $this->line('    Brevo: pakai Login + SMTP key dari tab SMTP, bukan API key (BREVO_SMTP_*).');
            $this->line('  - "Sender not valid" / 550 = VERIFICATION_MAIL_FROM_ADDRESS belum diverifikasi di Brevo.');
            $this->line('  - "Connection could not be established" = jaringan/DNS diblokir atau host/port salah.');
            $this->line('  - Setelah mengubah .env jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('BERHASIL: server email menerima dan mengantar pesan.');
        $this->newLine();
        $this->line('Langkah berikutnya kalau email belum terlihat:');
        $this->line('  1. Cek folder Spam / Promosi / "Semua email" di kotak masuk.');
        $this->line('  2. Tunggu 1-2 menit; antrean penerima kadang menunda.');
        $this->line('  3. Kalau penerima memakai domain kantor/sekolah (Google Workspace),');
        $this->line('     admin domain bisa menahan email dari luar — minta whitelist pengirim.');

        return self::SUCCESS;
    }

    /** Tampilkan alamat tanpa membocorkan bagian lokalnya: p***@gmail.com */
    /**
     * Jalur kirim yang benar-benar dipakai email verifikasi saat ini.
     *
     * Ditampilkan supaya tidak perlu menebak apakah Brevo sudah aktif atau
     * masih jatuh ke mailer bawaan.
     */
    /**
     * Domain email gratis tidak bisa diautentikasi (DKIM/SPF) di Brevo.
     *
     * Daftar sengaja pendek dan eksplisit: hanya penyedia yang benar-benar
     * menolak autentikasi domain.
     */
    private function isFreeMailDomain(string $email): bool
    {
        $domain = strtolower((string) substr((string) strrchr($email, '@'), 1));

        return in_array($domain, ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com'], true);
    }

    private function describeVerificationMailer(): string
    {
        if (VerificationMailer::apiConfigured()) {
            return 'brevo-api (REST API, BREVO_API_KEY terisi)';
        }

        if (!VerificationMailer::smtpConfigured()) {
            return VerificationMailer::name() . ' (Brevo belum diisi: BREVO_API_KEY dan BREVO_SMTP_USERNAME/PASSWORD kosong)';
        }

        $host = config('mail.mailers.brevo.host');
        $port = config('mail.mailers.brevo.port');
        $login = $this->maskEmail((string) config('mail.mailers.brevo.username'));

        return "brevo @ {$host}:{$port} sebagai {$login}";
    }

    private function maskEmail(string $email): string
    {
        if ($email === '') {
            return 'KOSONG';
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1) . '***@' . ($domain !== '' ? $domain : '(tanpa domain)');
    }
}
