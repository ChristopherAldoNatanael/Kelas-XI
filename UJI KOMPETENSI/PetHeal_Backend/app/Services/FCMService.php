<?php

namespace App\Services;

use Firebase\JWT\JWT;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FCM v1 HTTP API — uses a service-account JWT for OAuth2 access tokens.
 * Compatible with PHP 8.2 (no kreait/firebase-php required at runtime).
 */
class FCMService
{
    private string $projectId;
    private string $serviceAccountPath;

    // FCM v1 endpoint
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    // OAuth2 scope needed for FCM
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    // Google token endpoint
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id', 'petheal-d8c3d');

        // FIREBASE_CREDENTIALS in .env may be a relative path like
        // "storage/app/firebase-service-account.json" — resolve it to absolute.
        $raw = config(
            'services.firebase.credentials',
            storage_path('app/firebase-service-account.json')
        );

        $this->serviceAccountPath = str_starts_with($raw, '/')  || str_contains($raw, ':\\')
            ? $raw                                 // already absolute (Linux / Windows)
            : base_path($raw);                     // make it absolute from project root
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  OAuth2 Access Token (cached 55 min)
    // ─────────────────────────────────────────────────────────────────────────

    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            try {
                $sa = json_decode(file_get_contents($this->serviceAccountPath), true);

                $now     = time();
                $payload = [
                    'iss'   => $sa['client_email'],
                    'scope' => self::FCM_SCOPE,
                    'aud'   => self::TOKEN_URL,
                    'iat'   => $now,
                    'exp'   => $now + 3600,
                ];

                $jwt = JWT::encode($payload, $sa['private_key'], 'RS256');

                $response = Http::asForm()->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('FCM: failed to get access token — ' . $response->body());
                return null;
            } catch (\Throwable $e) {
                Log::error('FCM: access token exception — ' . $e->getMessage());
                return null;
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Low-level: send to a single device token
    // ─────────────────────────────────────────────────────────────────────────

    public function sendToDevice(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('FCM: no access token, aborting sendToDevice');
            return false;
        }

        // FCM v1 requires all data values to be strings
        $stringData = array_map('strval', $data);

        $payload = [
            'message' => [
                'token'        => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'android' => [
                    'priority'     => 'high',
                    'notification' => [
                        'sound'      => 'default',
                        'channel_id' => 'petheal_notifications',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
                'data' => $stringData,
            ],
        ];

        try {
            $url      = sprintf(self::FCM_URL, $this->projectId);
            $response = Http::withToken($accessToken)->post($url, $payload);

            if ($response->successful()) {
                Log::info('FCM: delivered to token ' . substr($deviceToken, 0, 20) . '…');
                return true;
            }

            Log::error('FCM: send failed (' . $response->status() . ') — ' . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error('FCM: sendToDevice exception — ' . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Send to multiple device tokens
    // ─────────────────────────────────────────────────────────────────────────

    public function sendToMultiple(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendToDevice($token, $title, $body, $data);
        }
        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Send to a user (all their registered device tokens)
    // ─────────────────────────────────────────────────────────────────────────

    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $this->storeNotification($userId, $title, $body, $data);

        $deviceTokens = \App\Models\DeviceToken::where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($deviceTokens)) {
            Log::warning("FCM: no device tokens found for user #{$userId}");
            return false;
        }

        $results = $this->sendToMultiple($deviceTokens, $title, $body, $data);

        // True if at least one device was reached successfully
        return in_array(true, $results, true);
    }

    private function storeNotification(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $data['type'] ?? 'general',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Notification history store failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  High-level helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function sendBookingReminder(int $userId, string $petName, ?string $bookingDate, ?string $bookingTime): bool
    {
        if (!$bookingDate || !$bookingTime) {
            return false;
        }

        return $this->sendToUser(
            $userId,
            'Booking Reminder',
            "You have an appointment for {$petName} on {$bookingDate} at {$bookingTime}",
            ['type' => 'booking_reminder', 'pet_name' => $petName, 'date' => $bookingDate, 'time' => $bookingTime]
        );
    }

    public function sendVaccinationReminder(int $userId, string $petName, ?string $nextVisitDate): bool
    {
        if (!$nextVisitDate) {
            return false;
        }

        return $this->sendToUser(
            $userId,
            'Vaccination Reminder',
            "Time to vaccinate {$petName}! Next visit scheduled for {$nextVisitDate}",
            ['type' => 'vaccination_reminder', 'pet_name' => $petName, 'next_visit' => $nextVisitDate]
        );
    }

    /**
     * Send manual reminder from admin dashboard.
     * $reminderType: "1_hour" | "tomorrow" | "custom"
     */
    public function sendManualReminder(
        int     $userId,
        string  $petName,
        string  $doctorName,
        string  $bookingDate,
        string  $bookingTime,
        string  $reminderType = 'tomorrow',
        ?string $customMessage = null
    ): bool {
        switch ($reminderType) {
            case '1_hour':
                $title = '⏰ Appointment in 1 Hour';
                $body  = "{$petName}'s appointment with {$doctorName} is TODAY at {$bookingTime}. See you soon!";
                break;
            case 'tomorrow':
                $title = '📅 Appointment Tomorrow';
                $body  = "Reminder: {$petName} has an appointment with {$doctorName} tomorrow ({$bookingDate}) at {$bookingTime}.";
                break;
            case 'custom':
                $title = '🔔 Appointment Reminder';
                $body  = $customMessage ?? "You have an appointment for {$petName} on {$bookingDate} at {$bookingTime}.";
                break;
            default:
                $title = '🔔 Appointment Reminder';
                $body  = "You have an appointment for {$petName} on {$bookingDate} at {$bookingTime}.";
        }

        return $this->sendToUser($userId, $title, $body, [
            'type'        => 'booking_reminder',
            'pet_name'    => $petName,
            'doctor_name' => $doctorName,
            'date'        => $bookingDate,
            'time'        => $bookingTime,
        ]);
    }

    public function sendBookingStatusUpdate(int $userId, string $petName, string $status, ?string $bookingDate): bool
    {
        if (!$bookingDate) {
            return false;
        }

        return $this->sendToUser(
            $userId,
            'Booking ' . ucfirst($status),
            "Your booking for {$petName} on {$bookingDate} has been {$status}",
            ['type' => 'booking_status', 'pet_name' => $petName, 'status' => $status, 'date' => $bookingDate]
        );
    }
}
