<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class HealthController extends Controller
{
    /**
     * Ringkasan kesehatan sistem untuk monitoring.
     *
     * Endpoint ini publik (tanpa token), jadi isinya hanya status per komponen.
     * Detail kegagalan — pesan exception database, path absolut berkas kredensial,
     * mode pembayaran, ada/tidaknya server key — TIDAK ikut dikirim karena itu
     * informasi recon yang berguna bagi penyerang. Detailnya masuk log server.
     */
    public function index()
    {
        $checks = [];
        $overallOk = true;

        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'status' => 'ok',
                'message' => 'Database connection available',
            ];
        } catch (\Throwable $e) {
            $overallOk = false;
            $checks['database'] = [
                'status' => 'failed',
                'message' => 'Database connection failed',
            ];

            // Pesan driver (mis. "Access denied for user 'root'@'localhost'") hanya
            // untuk operator, tidak untuk pemanggil anonim.
            Log::error('Health check: database tidak dapat dihubungi', [
                'message' => $e->getMessage(),
            ]);
        }

        $firebaseCredentials = config('services.firebase.credentials', env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')));
        $firebaseCredentialsPath = $this->resolvePath($firebaseCredentials);
        if (is_file($firebaseCredentialsPath)) {
            $checks['firebase'] = [
                'status' => 'ok',
                'message' => 'Firebase credentials file found',
            ];
        } else {
            $overallOk = false;
            $checks['firebase'] = [
                'status' => 'failed',
                'message' => 'Firebase credentials file not found',
            ];

            Log::warning('Health check: kredensial Firebase tidak ditemukan', [
                'path' => $firebaseCredentialsPath,
            ]);
        }

        $midtransServerKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY'));
        $midtransClientKey = config('services.midtrans.client_key', env('MIDTRANS_CLIENT_KEY'));
        $midtransProduction = (bool) config('services.midtrans.is_production', false);
        $midtransSnapUrl = config('services.midtrans.snap_url');
        $midtransApiUrl = config('services.midtrans.api_url');
        $midtransReady = !empty($midtransServerKey) && !empty($midtransClientKey) && !empty($midtransSnapUrl) && !empty($midtransApiUrl);
        if ($midtransReady) {
            $checks['midtrans'] = [
                'status' => 'ok',
                'message' => 'Midtrans configuration complete',
            ];
        } else {
            $overallOk = false;
            $checks['midtrans'] = [
                'status' => 'failed',
                'message' => 'Midtrans configuration incomplete',
            ];

            Log::warning('Health check: konfigurasi Midtrans pusat belum lengkap', [
                'server_key' => !empty($midtransServerKey) ? 'configured' : 'missing',
                'client_key' => !empty($midtransClientKey) ? 'configured' : 'missing',
                'snap_url' => $midtransSnapUrl,
                'api_url' => $midtransApiUrl,
                'mode' => $midtransProduction ? 'production' : 'sandbox',
            ]);
        }

        $publicStorage = storage_path('app/public');
        $checks['storage'] = [
            'status' => is_dir($publicStorage) && is_writable($publicStorage) ? 'ok' : 'warning',
            'message' => is_dir($publicStorage)
                ? (is_writable($publicStorage) ? 'Public storage directory is writable' : 'Public storage directory is not writable')
                : 'Public storage directory missing',
        ];

        // Antrean job yang tertahan berarti notifikasi push tidak terkirim.
        // Hanya jumlah dan umur yang dikirim; payload job tidak pernah bocor.
        try {
            $pendingJobs = (int) DB::table('jobs')->count();
            $oldestAvailable = DB::table('jobs')->min('available_at');
            $oldestAge = $oldestAvailable ? max(0, now()->timestamp - (int) $oldestAvailable) : 0;

            $checks['queue'] = [
                'status' => $pendingJobs === 0 || $oldestAge < 900 ? 'ok' : 'warning',
                'message' => $pendingJobs === 0
                    ? 'Queue empty'
                    : "{$pendingJobs} job(s) pending, oldest {$oldestAge}s",
                'pending' => $pendingJobs,
                'oldest_age_seconds' => $oldestAge,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = [
                'status' => 'warning',
                'message' => 'Queue table unavailable',
            ];
        }

        // `register-direct` sudah pensiun (410 use_otp_flow). Menjadikannya
        // syarat health membuat monitoring berteriak saat endpoint itu akhirnya
        // dihapus, padahal tidak ada klien yang memakainya.
        $requiredRoutes = [
            'health' => 'api/health',
            'payment_preflight' => 'api/payment/preflight',
            'payment_snap_token' => 'api/payment/snap-token',
            'firebase_login' => 'api/auth/firebase-login',
            'register_otp' => 'api/auth/register/request-otp',
            'register_verify_otp' => 'api/auth/register/verify-otp',
            'doctor_reviews' => 'api/doctors/{id}/reviews',
            'notifications' => 'api/notifications',
        ];
        $missingRoutes = [];
        foreach ($requiredRoutes as $name => $uri) {
            if (!$this->routeExists($uri)) {
                $missingRoutes[] = $name;
            }
        }
        if (!empty($missingRoutes)) {
            $overallOk = false;
        }
        $checks['routes'] = [
            'status' => empty($missingRoutes) ? 'ok' : 'failed',
            'message' => empty($missingRoutes) ? 'Required API routes are registered' : 'Some required API routes are missing',
            'missing' => $missingRoutes,
        ];

        return response()->json([
            'success' => $overallOk,
            'message' => $overallOk ? 'System healthy' : 'One or more system checks failed',
            'data' => $checks,
        ], $overallOk ? 200 : 503);
    }

    private function resolvePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (preg_match('/^[A-Za-z]:\\\\|^\//', $trimmed) === 1) {
            return $trimmed;
        }

        return base_path($trimmed);
    }

    private function routeExists(string $uri): bool
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri) {
                return true;
            }
        }

        return false;
    }
}
