<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class HealthController extends Controller
{
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
                'message' => $e->getMessage(),
            ];
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
                'path' => $firebaseCredentialsPath,
            ];
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
                'mode' => $midtransProduction ? 'production' : 'sandbox',
                'snap_url' => $midtransSnapUrl,
                'api_url' => $midtransApiUrl,
            ];
        } else {
            $overallOk = false;
            $checks['midtrans'] = [
                'status' => 'failed',
                'message' => 'Midtrans configuration incomplete',
                'mode' => $midtransProduction ? 'production' : 'sandbox',
                'server_key' => !empty($midtransServerKey) ? 'configured' : 'missing',
                'client_key' => !empty($midtransClientKey) ? 'configured' : 'missing',
                'snap_url' => !empty($midtransSnapUrl) ? $midtransSnapUrl : 'missing',
                'api_url' => !empty($midtransApiUrl) ? $midtransApiUrl : 'missing',
            ];
        }

        $publicStorage = storage_path('app/public');
        $checks['storage'] = [
            'status' => is_dir($publicStorage) && is_writable($publicStorage) ? 'ok' : 'warning',
            'message' => is_dir($publicStorage)
                ? (is_writable($publicStorage) ? 'Public storage directory exists and is writable' : 'Public storage directory exists but is not writable')
                : 'Public storage directory missing',
            'path' => $publicStorage,
        ];

        $requiredRoutes = [
            'health' => 'api/health',
            'payment_preflight' => 'api/payment/preflight',
            'payment_snap_token' => 'api/payment/snap-token',
            'firebase_login' => 'api/auth/firebase-login',
            'register_direct' => 'api/auth/register-direct',
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
