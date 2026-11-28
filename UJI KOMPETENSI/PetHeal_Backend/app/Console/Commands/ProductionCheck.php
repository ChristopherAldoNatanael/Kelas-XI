<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductionCheck extends Command
{
    protected $signature = 'app:production-check
        {--json : Keluarkan hasil sebagai JSON}
        {--allow-local : Jangan gagalkan APP_ENV=local (untuk staging)}';

    protected $description = 'Periksa kesiapan konfigurasi dan layanan sebelum produksi';

    /** @var array<int, array{key:string,status:string,message:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkEnvironment();
        $this->checkSecurity();
        $this->checkStorage();
        $this->checkDatabase();
        $this->checkQueue();
        $this->checkServices();
        $this->checkRuntime();

        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Status', 'Pemeriksaan', 'Keterangan'],
                collect($this->results)->map(fn ($row) => [
                    strtoupper($row['status']),
                    $row['key'],
                    $row['message'],
                ])->all()
            );
        }

        $failed = collect($this->results)->where('status', 'fail')->count();
        $warned = collect($this->results)->where('status', 'warn')->count();

        if (! $this->option('json')) {
            $this->newLine();
            $this->line($failed === 0
                ? "Tidak ada kegagalan wajib. Peringatan: {$warned}."
                : "GAGAL: {$failed} pemeriksaan wajib. Peringatan: {$warned}.");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function add(string $key, string $status, string $message): void
    {
        $this->results[] = compact('key', 'status', 'message');
    }

    private function checkEnvironment(): void
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');

        $this->add(
            'APP_ENV',
            $env === 'production' || $this->option('allow-local') ? 'ok' : 'fail',
            "Saat ini: {$env}. Produksi harus production."
        );

        $this->add(
            'APP_DEBUG',
            $debug ? 'fail' : 'ok',
            $debug ? 'APP_DEBUG=true membocorkan stack trace dan query.' : 'APP_DEBUG=false.'
        );

        $url = (string) config('app.url');
        $this->add(
            'APP_URL',
            str_starts_with($url, 'https://') ? 'ok' : 'fail',
            "Saat ini: {$url}. Produksi wajib HTTPS."
        );

        $this->add(
            'APP_KEY',
            config('app.key') ? 'ok' : 'fail',
            config('app.key') ? 'APP_KEY tersedia.' : 'APP_KEY kosong.'
        );

        $this->add(
            'CONFIG_CACHE',
            app()->configurationIsCached() ? 'ok' : 'warn',
            app()->configurationIsCached() ? 'Konfigurasi sudah di-cache.' : 'Jalankan php artisan config:cache.'
        );

        $this->add(
            'ROUTE_CACHE',
            app()->routesAreCached() ? 'ok' : 'warn',
            app()->routesAreCached() ? 'Route sudah di-cache.' : 'Jalankan php artisan route:cache.'
        );
    }

    private function checkSecurity(): void
    {
        $secureCookie = config('session.secure');
        $this->add(
            'SESSION_SECURE_COOKIE',
            $secureCookie ? 'ok' : 'warn',
            $secureCookie ? 'Cookie sesi hanya lewat HTTPS.' : 'Set SESSION_SECURE_COOKIE=true di HTTPS.'
        );

        $this->add(
            'SESSION_DRIVER',
            config('session.driver') === 'array' ? 'fail' : 'ok',
            'Driver sesi: ' . config('session.driver')
        );

        $cspReportOnly = config('security.csp_report_only', true);
        $this->add(
            'CSP_MODE',
            $cspReportOnly ? 'warn' : 'ok',
            $cspReportOnly
                ? 'CSP masih report-only. Pindahkan script inline sebelum enforcement.'
                : 'CSP sudah enforcement.'
        );

        $this->add(
            'TRUSTED_PROXIES',
            config('app.url') && str_starts_with((string) config('app.url'), 'https://') ? 'warn' : 'ok',
            'Pastikan TRUSTED_PROXIES hanya berisi proxy milik Anda saat memakai reverse proxy/ngrok.'
        );
    }

    private function checkStorage(): void
    {
        $paths = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'storage/app/public' => storage_path('app/public'),
        ];

        foreach ($paths as $label => $path) {
            $this->add(
                'WRITABLE ' . $label,
                is_dir($path) && is_writable($path) ? 'ok' : 'fail',
                is_dir($path)
                    ? (is_writable($path) ? 'Dapat ditulis.' : 'Tidak dapat ditulis.')
                    : 'Direktori tidak ditemukan.'
            );
        }

        $this->add(
            'STORAGE_LINK',
            is_link(public_path('storage')) || file_exists(public_path('storage')) ? 'ok' : 'fail',
            'Jalankan php artisan storage:link bila belum ada.'
        );

        $free = @disk_free_space(storage_path());
        $this->add(
            'DISK_SPACE',
            $free !== false && $free > 2 * 1024 * 1024 * 1024 ? 'ok' : 'warn',
            $free === false ? 'Tidak dapat membaca ruang disk.' : 'Sisa disk: ' . round($free / 1024 / 1024 / 1024, 1) . ' GB.'
        );
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->add('DATABASE', 'ok', 'Koneksi database tersedia.');

            $pending = DB::table('migrations')->count();
            $this->add('MIGRATIONS', $pending > 0 ? 'ok' : 'fail', "Migration tercatat: {$pending}.");
        } catch (\Throwable $e) {
            $this->add('DATABASE', 'fail', 'Koneksi database gagal: ' . $e->getMessage());
            $this->add('MIGRATIONS', 'fail', 'Tidak dapat memeriksa migration.');
        }

        $this->add(
            'CACHE_STORE',
            in_array(config('cache.default'), ['redis', 'memcached', 'database'], true) ? 'ok' : 'warn',
            'Cache: ' . config('cache.default') . '. File cache boleh, tetapi Redis lebih baik untuk multi-server.'
        );
    }

    private function checkQueue(): void
    {
        $queue = (string) config('queue.default');
        $this->add(
            'QUEUE_CONNECTION',
            in_array($queue, ['sync', 'array'], true) ? 'fail' : 'ok',
            "Queue driver: {$queue}. Produksi tidak boleh sync."
        );

        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('available_at');
            $age = $oldest ? max(0, now()->timestamp - (int) $oldest) : 0;
            $failed = DB::table('failed_jobs')->count();

            $this->add(
                'QUEUE_BACKLOG',
                $pending > 0 && $age > 900 ? 'fail' : 'ok',
                "Pending: {$pending}, tertua: {$age} detik."
            );
            $this->add(
                'FAILED_JOBS',
                $failed > 0 ? 'warn' : 'ok',
                "Failed jobs: {$failed}. Periksa queue:failed."
            );
        } catch (\Throwable $e) {
            $this->add('QUEUE_BACKLOG', 'warn', 'Tabel queue tidak dapat dibaca.');
        }

        $this->add(
            'QUEUE_WORKER',
            'warn',
            'Pastikan Supervisor/systemd menjalankan queue:work. Perintah tidak bisa mendeteksi proses OS dari sini.'
        );

        $this->add(
            'SCHEDULER',
            'warn',
            'Pastikan cron menjalankan php artisan schedule:run setiap menit.'
        );
    }

    private function checkServices(): void
    {
        $firebase = config('services.firebase.credentials', storage_path('app/firebase-service-account.json'));
        $firebasePath = str_starts_with((string) $firebase, '/') || preg_match('/^[A-Za-z]:/', (string) $firebase)
            ? (string) $firebase
            : base_path((string) $firebase);

        $this->add(
            'FIREBASE_CREDENTIALS',
            is_file($firebasePath) ? 'ok' : 'fail',
            is_file($firebasePath) ? 'Berkas kredensial tersedia.' : 'Berkas kredensial Firebase tidak ditemukan.'
        );

        $midtransReady = config('services.midtrans.server_key')
            && config('services.midtrans.client_key')
            && config('services.midtrans.snap_url');

        $this->add(
            'MIDTRANS_CONFIG',
            $midtransReady ? 'ok' : 'fail',
            $midtransReady ? 'Konfigurasi Midtrans lengkap.' : 'Server key/client key/Snap URL belum lengkap.'
        );

        $this->add(
            'MIDTRANS_MODE',
            config('services.midtrans.is_production') ? 'ok' : 'warn',
            config('services.midtrans.is_production') ? 'Mode production.' : 'Masih sandbox.'
        );

        $mailer = (string) config('mail.default');
        $this->add(
            'MAIL',
            in_array($mailer, ['log', 'array'], true) ? 'fail' : 'ok',
            "Mailer: {$mailer}. Jangan gunakan log/array untuk OTP produksi."
        );

        $this->add(
            'MEDICAL_KEY',
            config('medical_records.attachment_encryption_enabled', false)
                && ! config('medical_records.attachment_encryption_key')
                ? 'warn'
                : 'ok',
            'Bila kunci lampiran diturunkan dari APP_KEY, buat backup kunci dan dokumentasikan rotasinya.'
        );
    }

    private function checkRuntime(): void
    {
        foreach (['pdo_mysql', 'gd', 'mbstring', 'openssl', 'curl', 'zip', 'intl', 'fileinfo', 'bcmath'] as $extension) {
            $this->add(
                'PHP_EXT ' . $extension,
                extension_loaded($extension) ? 'ok' : 'fail',
                extension_loaded($extension) ? 'Tersedia.' : 'Ekstensi wajib tidak aktif.'
            );
        }

        $this->add(
            'PHP_VERSION',
            version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail',
            'PHP ' . PHP_VERSION
        );

        $this->add(
            'OPCACHE',
            function_exists('opcache_get_status') && @opcache_get_status() ? 'ok' : 'warn',
            'OPcache mempercepat PHP di produksi.'
        );

        $memory = ini_get('memory_limit');
        $this->add(
            'MEMORY_LIMIT',
            $memory === '-1' || (int) $memory >= 256 ? 'ok' : 'warn',
            'memory_limit=' . $memory
        );
    }
}
