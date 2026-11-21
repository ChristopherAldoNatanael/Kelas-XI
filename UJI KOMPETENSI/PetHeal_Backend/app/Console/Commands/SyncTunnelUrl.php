<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Menyelaraskan `APP_URL` + `ASSET_URL` dengan domain tunnel (ngrok) yang
 * sedang hidup.
 *
 * Kenapa perlu: `ASSET_URL` di `.env` masih `http://127.0.0.1:8000`. Begitu
 * dashboard dibuka lewat domain ngrok (dari ponsel atau komputer lain),
 * browser mencoba mengambil CSS/JS dari `127.0.0.1` milik perangkat itu
 * sendiri — halaman tampil tanpa gaya, dan panitia mengira "ngrok rusak".
 * Tautan di email (undangan dokter) juga ikut salah bila dibuat dari CLI.
 *
 * Perintah ini TIDAK menyentuh kredensial atau variabel lain: hanya dua baris
 * URL, lalu cache config dibersihkan.
 */
class SyncTunnelUrl extends Command
{
    protected $signature = 'petheal:tunnel-url
        {--api=http://127.0.0.1:4040 : Alamat API lokal ngrok}
        {--env-file= : Berkas env yang diubah (default: .env di root proyek)}
        {--dry-run : Tampilkan perubahan tanpa menulis berkas}';

    protected $description = 'Samakan APP_URL & ASSET_URL dengan domain ngrok yang sedang aktif';

    public function handle(): int
    {
        $api = rtrim((string) $this->option('api'), '/');
        $envPath = (string) ($this->option('env-file') ?: base_path('.env'));

        if (!is_file($envPath)) {
            $this->error("Berkas env tidak ditemukan: {$envPath}");

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(5)->get($api . '/api/tunnels');
        } catch (\Throwable $e) {
            return $this->stop('ngrok tidak bisa dihubungi di ' . $api . '. Jalankan `ngrok http 8000` lebih dulu.');
        }

        if (!$response->successful()) {
            return $this->stop('API ngrok menjawab HTTP ' . $response->status() . '.');
        }

        $publicUrl = $this->pickHttpsTunnel((array) $response->json('tunnels', []));

        if ($publicUrl === null) {
            return $this->stop('Tidak ada tunnel https yang aktif. Pastikan `ngrok http 8000` berjalan.');
        }

        $env = (string) file_get_contents($envPath);
        $changes = [];

        foreach (['APP_URL' => $publicUrl, 'ASSET_URL' => $publicUrl] as $key => $value) {
            [$env, $old] = $this->putEnvValue($env, $key, $value);
            $changes[] = [$key, $old, $value];
        }

        $this->table(['Variabel', 'Sebelum', 'Sesudah'], $changes);

        if ($this->option('dry-run')) {
            $this->warn('Mode dry-run: berkas tidak diubah.');

            return self::SUCCESS;
        }

        file_put_contents($envPath, $env);
        Artisan::call('config:clear');

        $this->info('Tersimpan. Cache config dibersihkan.');
        $this->newLine();
        $this->line('Langkah berikutnya:');
        $this->line('  1. Pastikan Android memakai URL yang sama:');
        $this->line('     ' . $this->androidNote($publicUrl));
        $this->line('  2. Restart `php artisan serve` bila sedang berjalan, lalu buka:');
        $this->line('     ' . $publicUrl . '/admin/login');

        return self::SUCCESS;
    }

    /** @param array<int,mixed> $tunnels */
    private function pickHttpsTunnel(array $tunnels): ?string
    {
        foreach ($tunnels as $tunnel) {
            $url = is_array($tunnel) ? ($tunnel['public_url'] ?? null) : null;

            if (is_string($url) && str_starts_with($url, 'https://')) {
                return rtrim($url, '/');
            }
        }

        return null;
    }

    /**
     * Ganti satu baris KEY=... tanpa menyentuh baris lain.
     *
     * @return array{0:string,1:string} [env baru, nilai lama]
     */
    private function putEnvValue(string $env, string $key, string $value): array
    {
        $old = '';

        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $env, $match)) {
            $old = trim($match[1]);
            $env = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $key . '=' . $value, $env, 1) ?? $env;
        } else {
            $env = rtrim($env, "\r\n") . PHP_EOL . $key . '=' . $value . PHP_EOL;
        }

        return [$env, $old];
    }

    /** Bandingkan dengan BACKEND_BASE_URL milik aplikasi Android (kalau ada). */
    private function androidNote(string $publicUrl): string
    {
        $path = base_path('../PetHeal_Android/local.properties');

        if (!is_file($path)) {
            return 'local.properties Android tidak ditemukan dari sini.';
        }

        if (!preg_match('/^BACKEND_BASE_URL=(.*)$/m', (string) file_get_contents($path), $m)) {
            return 'BACKEND_BASE_URL tidak ada di local.properties.';
        }

        $android = trim($m[1]);
        $expected = $publicUrl . '/api/';

        return $android === $expected
            ? "sudah cocok ({$android})"
            : "SEKARANG {$android} — seharusnya {$expected}";
    }

    private function stop(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
