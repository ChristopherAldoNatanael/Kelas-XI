<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

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
        if (!empty($midtransServerKey)) {
            $checks['midtrans'] = [
                'status' => 'ok',
                'message' => 'Midtrans server key configured',
            ];
        } else {
            $overallOk = false;
            $checks['midtrans'] = [
                'status' => 'failed',
                'message' => 'Midtrans server key is missing',
            ];
        }

        $checks['storage'] = [
            'status' => is_dir(storage_path('app/public')) ? 'ok' : 'warning',
            'message' => is_dir(storage_path('app/public')) ? 'Public storage directory exists' : 'Public storage directory missing',
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
}
