<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Notification;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    private string $projectId;
    private string $serviceAccountPath;

    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id', 'petheal-d8c3d');

        $raw = config(
            'services.firebase.credentials',
            storage_path('app/firebase-service-account.json')
        );

        $this->serviceAccountPath = str_starts_with($raw, '/') || str_contains($raw, ':\\')
            ? $raw
            : base_path($raw);
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            try {
                $sa = json_decode(file_get_contents($this->serviceAccountPath), true, 512, JSON_THROW_ON_ERROR);

                $now = time();
                $payload = [
                    'iss' => $sa['client_email'],
                    'scope' => self::FCM_SCOPE,
                    'aud' => self::TOKEN_URL,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ];

                $jwt = JWT::encode($payload, $sa['private_key'], 'RS256');
                $response = Http::asForm()->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('FCM access token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('FCM access token exception', [
                    'message' => $e->getMessage(),
                ]);
            }

            return null;
        });
    }

    private function getTemplateText(string $key, string $field, string $default): string
    {
        return AppSetting::getValue("notifications.{$key}.{$field}", $default) ?? $default;
    }

    private function renderTemplate(string $template, array $replacements): string
    {
        $rendered = $template;

        foreach ($replacements as $key => $value) {
            $rendered = str_replace('{' . $key . '}', (string) $value, $rendered);
        }

        return trim(preg_replace('/\s{2,}/', ' ', $rendered) ?? $rendered);
    }

    public function sendToDevice(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('FCM send aborted: access token unavailable');
            return false;
        }

        $stringData = array_map(static fn ($value) => (string) $value, array_filter($data, static fn ($value) => $value !== null));

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
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
            $response = Http::withToken($accessToken)->post(sprintf(self::FCM_URL, $this->projectId), $payload);

            if ($response->successful()) {
                Log::info('FCM delivered to device', [
                    'token_hash' => hash('sha256', $deviceToken),
                ]);
                return true;
            }

            Log::error('FCM send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('FCM send exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function sendToMultiple(array $deviceTokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendToDevice($token, $title, $body, $data);
        }
        return $results;
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $this->storeNotification($userId, $title, $body, $data);

        $deviceTokens = \App\Models\DeviceToken::where('user_id', $userId)->pluck('token')->toArray();
        if (empty($deviceTokens)) {
            Log::warning("FCM: no device tokens found for user #{$userId}");
            return false;
        }

        $results = $this->sendToMultiple($deviceTokens, $title, $body, $data);
        return in_array(true, $results, true);
    }

    private function storeNotification(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            // PHASE 3 (J-06): attribute new rows to the recipient's clinic so
            // clinic-scoped reporting keeps working (column was backfilled
            // once but never written afterwards).
            $clinicId = \App\Models\User::whereKey($userId)->value('clinic_id');
            Notification::create([
                'user_id' => $userId,
                'clinic_id' => $clinicId,
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

    public function sendBookingReminder(
        int $userId,
        string $petName,
        ?string $bookingDate,
        ?string $bookingTime,
        ?int $bookingId = null,
        ?int $petId = null,
        ?int $doctorId = null,
        ?string $doctorName = null
    ): bool {
        if (!$bookingDate || !$bookingTime) {
            return false;
        }

        $replacements = [
            'pet_name' => $petName,
            'doctor_name' => $doctorName ?? 'dokter Anda',
            'date' => $bookingDate,
            'time' => $bookingTime,
        ];

        $title = $this->renderTemplate(
            $this->getTemplateText('booking_reminder', 'title', 'Pengingat Booking PetHeal'),
            $replacements
        );
        $body = $this->renderTemplate(
            $this->getTemplateText('booking_reminder', 'body', '{pet_name} memiliki jadwal dengan {doctor_name} pada {date} pukul {time}.'),
            $replacements
        );

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'booking_reminder',
            'title' => $title,
            'body' => $body,
            'pet_name' => $petName,
            'doctor_name' => $doctorName,
            'date' => $bookingDate,
            'time' => $bookingTime,
            'booking_id' => $bookingId,
            'pet_id' => $petId,
            'doctor_id' => $doctorId,
        ]);
    }

    public function sendVaccinationReminder(
        int $userId,
        string $petName,
        ?string $nextVisitDate,
        ?int $petId = null,
        ?int $medicalRecordId = null
    ): bool {
        if (!$nextVisitDate) {
            return false;
        }

        $replacements = [
            'pet_name' => $petName,
            'next_visit' => $nextVisitDate,
        ];

        $title = $this->renderTemplate(
            $this->getTemplateText('vaccination_reminder', 'title', 'Pengingat Vaksinasi PetHeal'),
            $replacements
        );
        $body = $this->renderTemplate(
            $this->getTemplateText('vaccination_reminder', 'body', 'Saatnya kunjungan berikutnya untuk {pet_name}. Jadwal tindak lanjut: {next_visit}.'),
            $replacements
        );

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'vaccination_reminder',
            'title' => $title,
            'body' => $body,
            'pet_name' => $petName,
            'next_visit' => $nextVisitDate,
            'pet_id' => $petId,
            'medical_record_id' => $medicalRecordId,
        ]);
    }

    public function sendManualReminder(
        int $userId,
        string $petName,
        string $doctorName,
        string $bookingDate,
        string $bookingTime,
        string $reminderType = 'tomorrow',
        ?string $customMessage = null,
        ?int $bookingId = null,
        ?int $petId = null,
        ?int $doctorId = null
    ): bool {
        $replacements = [
            'pet_name' => $petName,
            'doctor_name' => $doctorName,
            'date' => $bookingDate,
            'time' => $bookingTime,
        ];

        $title = $this->renderTemplate(
            $this->getTemplateText('booking_reminder', 'title', 'Pengingat Booking PetHeal'),
            $replacements
        );
        $body = $reminderType === 'custom' && $customMessage
            ? $customMessage
            : $this->renderTemplate(
                $this->getTemplateText('booking_reminder', 'body', '{pet_name} memiliki jadwal dengan {doctor_name} pada {date} pukul {time}.'),
                $replacements
            );

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'booking_reminder',
            'title' => $title,
            'body' => $body,
            'pet_name' => $petName,
            'doctor_name' => $doctorName,
            'date' => $bookingDate,
            'time' => $bookingTime,
            'booking_id' => $bookingId,
            'pet_id' => $petId,
            'doctor_id' => $doctorId,
        ]);
    }

    public function sendBookingStatusUpdate(
        int $userId,
        string $petName,
        string $status,
        ?string $bookingDate,
        ?int $bookingId = null,
        ?int $petId = null,
        ?int $doctorId = null
    ): bool {
        if (!$bookingDate) {
            return false;
        }

        $replacements = [
            'pet_name' => $petName,
            'status' => $status,
            'date' => $bookingDate,
        ];

        $templateKey = 'booking_status_' . strtolower($status);
        $title = $this->renderTemplate(
            $this->getTemplateText($templateKey, 'title', 'Status Booking Diperbarui'),
            $replacements
        );
        $body = $this->renderTemplate(
            $this->getTemplateText($templateKey, 'body', 'Booking {pet_name} pada {date} memiliki status {status}.'),
            $replacements
        );

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'booking_status',
            'title' => $title,
            'body' => $body,
            'pet_name' => $petName,
            'status' => $status,
            'date' => $bookingDate,
            'booking_id' => $bookingId,
            'pet_id' => $petId,
            'doctor_id' => $doctorId,
        ]);
    }

    public function sendPaymentReminder(
        int $userId,
        string $petName,
        string $remainingAmount,
        ?int $bookingId = null,
        ?int $petId = null,
        ?int $doctorId = null
    ): bool {
        $replacements = [
            'pet_name' => $petName,
            'remaining_amount' => $remainingAmount,
        ];

        $title = $this->renderTemplate(
            $this->getTemplateText('payment_reminder', 'title', 'Pengingat Pembayaran PetHeal'),
            $replacements
        );
        $body = $this->renderTemplate(
            $this->getTemplateText('payment_reminder', 'body', 'Sisa pembayaran untuk {pet_name} sebesar {remaining_amount}. Silakan selesaikan pembayaran booking Anda.'),
            $replacements
        );

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'payment_reminder',
            'title' => $title,
            'body' => $body,
            'pet_name' => $petName,
            'remaining_amount' => $remainingAmount,
            'booking_id' => $bookingId,
            'pet_id' => $petId,
            'doctor_id' => $doctorId,
        ]);
    }
}
