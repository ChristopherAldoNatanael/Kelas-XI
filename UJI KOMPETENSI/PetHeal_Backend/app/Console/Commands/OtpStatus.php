<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menjawab pertanyaan "kode OTP-nya benar-benar terbit atau tidak?".
 *
 * Kalau permintaan OTP sampai ke server, selalu ada satu baris di `otp_codes`
 * untuk email+tujuan tersebut. Jadi perintah ini memisahkan dua kemungkinan
 * yang sering tertukar saat troubleshooting:
 *
 *   - tidak ada baris  -> permintaan tidak pernah sampai (cek app/endpoint);
 *   - ada baris        -> kode terbit, masalahnya di pengiriman/penerimaan email.
 *
 * Kode disimpan sebagai hash, jadi isinya memang tidak bisa ditampilkan di sini.
 */
class OtpStatus extends Command
{
    protected $signature = 'petheal:otp-status
        {email : Email yang dicek}
        {--purpose= : Batasi ke satu tujuan (register, reset, admin_register, admin_password, doctor_password, delete_account, email_change)}';

    protected $description = 'Tampilkan status kode OTP terakhir untuk sebuah email (terbit, kedaluwarsa, atau sudah dipakai)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $purpose = $this->option('purpose');

        $query = DB::table('otp_codes')->where('email', $email);

        if (is_string($purpose) && $purpose !== '') {
            $query->where('purpose', $purpose);
        }

        $rows = $query->orderBy('purpose')->get();

        $this->line("Email   : {$email}");
        $this->line('Ditemukan: ' . $rows->count() . ' baris kode');
        $this->newLine();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada kode OTP untuk email ini.');
            $this->line('Artinya permintaan kirim kode tidak pernah sampai ke server (atau emailnya beda).');
            $this->line('Periksa: alamat yang diketik di form, respons endpoint, dan log aplikasi.');

            return self::SUCCESS;
        }

        $now = now();

        $this->table(
            ['Tujuan', 'Status', 'Sisa berlaku', 'Percobaan salah', 'Terbit'],
            $rows->map(function ($row) use ($now) {
                $expiresAt = \Illuminate\Support\Carbon::parse($row->expires_at);

                if ($row->consumed_at !== null) {
                    $status = 'sudah dipakai';
                } elseif ($expiresAt->isPast()) {
                    $status = 'kedaluwarsa';
                } else {
                    $status = 'AKTIF';
                }

                $remaining = $expiresAt->isPast()
                    ? '-'
                    : $now->diffForHumans($expiresAt, [
                        'parts' => 2,
                        'short' => true,
                        'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
                    ]);

                return [
                    $row->purpose,
                    $status,
                    $remaining,
                    $row->attempts . '/5',
                    \Illuminate\Support\Carbon::parse($row->created_at)->translatedFormat('d M Y H:i'),
                ];
            })->all()
        );

        $this->newLine();
        $this->line('Kalau status AKTIF tetapi email tidak diterima: masalahnya di pengiriman email,');
        $this->line('bukan di server. Uji jalur email dengan: php artisan petheal:mail-test ' . $email);

        return self::SUCCESS;
    }
}
