<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Membaca log pengiriman Brevo untuk email verifikasi.
 *
 * Ini jawaban untuk pertanyaan "emailnya sampai atau tidak?": dashboard Brevo
 * mencatat tiap pesan sebagai delivered / blocked / bounced / deferred beserta
 * alasannya. Tanpa alat ini, "email tidak masuk" hanya bisa ditebak.
 *
 * Butuh BREVO_API_KEY di .env (Settings > SMTP & API > tab API keys). Kunci ini
 * berbeda dari SMTP key; hanya dipakai untuk membaca statistik, tidak untuk kirim.
 */
class BrevoEvents extends Command
{
    protected $signature = 'petheal:brevo-events
        {email? : Saring satu alamat tujuan}
        {--days=2 : Berapa hari ke belakang}
        {--limit=20 : Jumlah baris maksimum}';

    protected $description = 'Lihat status pengiriman email verifikasi di Brevo (delivered/blocked/bounced)';

    public function handle(): int
    {
        $apiKey = trim((string) config('services.brevo.key'));

        if ($apiKey === '') {
            $this->error('BREVO_API_KEY belum diisi di .env.');
            $this->line('Buat di https://app.brevo.com/settings/keys/api (tab API keys), lalu:');
            $this->line('  BREVO_API_KEY=xkeysib-...');
            $this->line('  php artisan config:clear');

            return self::FAILURE;
        }

        $query = [
            'days' => max(1, (int) $this->option('days')),
            'limit' => max(1, (int) $this->option('limit')),
            'sort' => 'desc',
        ];

        $email = trim((string) $this->argument('email'));

        $response = Http::withHeaders(['api-key' => $apiKey, 'accept' => 'application/json'])
            ->timeout(20)
            ->get('https://api.brevo.com/v3/smtp/statistics/events', $query);

        if ($response->failed()) {
            $this->error('Brevo menolak permintaan (HTTP ' . $response->status() . ').');

            $message = (string) ($response->json('message') ?? $response->body());

            if ($message !== '') {
                $this->line('Pesan Brevo: ' . mb_substr($message, 0, 300));
            }

            if ($response->status() === 401) {
                $this->line('Kunci API tidak valid. Pastikan memakai API key (xkeysib-...), bukan SMTP key.');
            }

            return self::FAILURE;
        }

        $events = $response->json('events') ?? [];

        // Saringan email dilakukan di sini, bukan lewat parameter Brevo:
        // parameter itu bisa mengembalikan daftar kosong meski kejadiannya ada.
        if ($email !== '') {
            $events = array_values(array_filter(
                $events,
                fn (array $event) => strcasecmp((string) ($event['email'] ?? ''), $email) === 0,
            ));
        }

        if ($events === []) {
            $this->warn('Tidak ada kejadian email pada rentang ini.');
            $this->line('Kalau baru saja mengirim, tunggu 1-2 menit lalu ulangi.');

            return self::SUCCESS;
        }

        $events = array_slice($events, 0, max(1, (int) $this->option('limit')));

        $this->table(
            ['Waktu', 'Kejadian', 'Tujuan', 'Alasan', 'Message-ID'],
            array_map(fn (array $event) => [
                $event['date'] ?? '-',
                $event['event'] ?? '-',
                $event['email'] ?? '-',
                $event['reason'] ?? '-',
                mb_substr((string) ($event['message-id'] ?? '-'), 0, 26),
            ], $events),
        );

        $this->newLine();
        $this->line('Arti kejadian:');
        $this->line('  requests   = diterima Brevo (belum tentu sampai)');
        $this->line('  delivered  = sudah diterima server Gmail penerima → cek Inbox/Spam');
        $this->line('  blocked    = ditolak karena isi/kebijakan (alasan ada di kolom Alasan)');
        $this->line('  bounced    = alamat tujuan tidak ada / kotak penuh');
        $this->line('  deferred   = ditunda sementara oleh penerima');
        $this->line('  spam       = penerima menandai spam');

        return self::SUCCESS;
    }
}
