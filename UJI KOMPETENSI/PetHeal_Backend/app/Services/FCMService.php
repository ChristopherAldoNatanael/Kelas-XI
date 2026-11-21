<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\ServiceRequest;
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

    /** Batas waktu koneksi ke Google (detik). */
    private const CONNECT_TIMEOUT_SECONDS = 3;

    /** Batas waktu satu permintaan FCM/OAuth (detik). */
    private const REQUEST_TIMEOUT_SECONDS = 5;

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
                $response = Http::asForm()
                    // Batas waktu tegas: notifikasi tidak boleh menahan request
                    // HTTP pasien kalau Google lambat merespons.
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                    ->post(self::TOKEN_URL, [
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

    /**
     * Kirim satu notifikasi: selalu memuat blok `notification` (agar sistem
     * menampilkannya saat aplikasi di background/terminated) sekaligus `data`
     * (agar aplikasi dapat membangun tray sendiri saat foreground dan tahu
     * halaman tujuan saat notifikasi diketuk).
     */
    public function sendToDevice(string $deviceToken, string $title, string $body, array $data = [], string $channelId = 'petheal_notifications'): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('FCM send aborted: access token unavailable');
            return false;
        }

        $stringData = array_map(static fn ($value) => (string) $value, array_filter($data, static fn ($value) => $value !== null));

        // Notifikasi yang ditampilkan sistem (aplikasi di background atau
        // prosesnya sudah dihentikan) membuka MainActivity dengan data ini
        // sebagai extra. Tanpa penanda ini, tap notifikasi hanya membuka
        // aplikasi tanpa menuju booking/permintaan layanan terkait.
        $stringData['navigate_to'] = 'notification_target';

        // Payload notifikasi TIDAK memuat data medis. Lock screen hanya boleh
        // menampilkan judul/isi umum; detail ada di aplikasi setelah login.
        $message = [
            'token' => $deviceToken,
            'android' => ['priority' => 'high'],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
            'data' => $stringData,
        ];

        $message['notification'] = [
            'title' => $title,
            'body' => $body,
        ];
        $message['android']['notification'] = [
            'sound' => 'default',
            'channel_id' => $channelId,
            'visibility' => 'private',
            'default_vibrate_timings' => true,
        ];

        $payload = ['message' => $message];

        try {
            $response = Http::withToken($accessToken)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                // Satu percobaan ulang untuk gangguan jaringan sesaat. Tidak
                // lebih: notifikasi bersifat informatif, dan pesan yang sama
                // dikirim ulang akan diabaikan aplikasi lewat `queue_event_id`.
                ->retry(1, 300, throw: false)
                ->post(sprintf(self::FCM_URL, $this->projectId), $payload);

            if ($response->successful()) {
                $this->touchToken($deviceToken);
                Log::info('FCM delivered to device', [
                    'token_hash' => hash('sha256', $deviceToken),
                ]);
                return true;
            }

            // Token yang sudah tidak berlaku (aplikasi di-uninstall, token
            // dirotasi) akan ditolak FCM terus-menerus. Tandai invalid supaya
            // tidak dikirimi lagi — ini yang membuat daftar token tetap sehat.
            if ($this->isTokenRejected($response->body())) {
                $this->invalidateToken($deviceToken);
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

    /**
     * FCM menolak token yang tidak terdaftar (UNREGISTERED) atau rusak
     * (INVALID_ARGUMENT). Keduanya berarti token itu tidak bisa dipakai lagi
     * untuk perangkat tersebut.
     */
    private function isTokenRejected(string $body): bool
    {
        return str_contains($body, 'UNREGISTERED')
            || str_contains($body, 'INVALID_ARGUMENT');
    }

    /** Nonaktifkan token yang ditolak FCM. */
    private function invalidateToken(string $deviceToken): void
    {
        try {
            $token = \App\Models\DeviceToken::where('token', $deviceToken)->first();

            if ($token) {
                $token->markInvalid();
                Log::warning('FCM token marked invalid', [
                    'token_hash' => hash('sha256', $deviceToken),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to invalidate FCM token', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** Catat waktu token terakhir berhasil dipakai. */
    private function touchToken(string $deviceToken): void
    {
        try {
            \App\Models\DeviceToken::where('token', $deviceToken)
                ->update(['last_used_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Failed to update FCM token usage', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function sendToMultiple(array $deviceTokens, string $title, string $body, array $data = [], string $channelId = 'petheal_notifications'): array
    {
        $results = [];
        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendToDevice($token, $title, $body, $data, $channelId);
        }
        return $results;
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = [], string $channelId = 'petheal_notifications'): bool
    {
        $this->storeNotification($userId, $title, $body, $data);

        // Riwayat notifikasi sudah tersimpan di atas (sumber kebenaran tetap
        // database). Pengiriman ke perangkat bisa dilewatkan antrian supaya
        // request HTTP pasien tidak menunggu Google: aktifkan `FCM_QUEUE=true`
        // BERSAMAAN dengan worker (`start-queue.bat`). Default mati agar
        // notifikasi tidak pernah diam-diam tertahan di tabel `jobs` ketika
        // worker belum dijalankan.
        if ((bool) config('services.fcm.queue', false)) {
            \App\Jobs\SendFcmNotification::dispatch($userId, $title, $body, $data, $channelId);
            return true;
        }

        return $this->deliverToUser($userId, $title, $body, $data, $channelId);
    }

    /**
     * Kirim ke semua perangkat aktif milik satu pengguna (tanpa menulis
     * riwayat). Dipakai `sendToUser()` dan worker antrian
     * (`App\Jobs\SendFcmNotification`).
     */
    public function deliverToUser(int $userId, string $title, string $body, array $data = [], string $channelId = 'petheal_notifications'): bool
    {
        $deviceTokens = \App\Models\DeviceToken::where('user_id', $userId)
            ->active()
            ->pluck('token')
            ->toArray();
        if (empty($deviceTokens)) {
            Log::warning("FCM: no device tokens found for user #{$userId}");
            return false;
        }

        $results = $this->sendToMultiple($deviceTokens, $title, $body, $data, $channelId);
        return in_array(true, $results, true);
    }

    private function storeNotification(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            // PHASE 3 (J-06): attribute new rows to a clinic so clinic-scoped
            // reporting keeps working.
            //
            // Urutan sumber: klinik yang dikirim pemanggil (booking/order yang
            // memicu notifikasi) → `clinic_id` di payload → klinik milik akun.
            // Pasien adalah akun GLOBAL (`users.clinic_id` = NULL), jadi tanpa
            // dua sumber pertama seluruh notifikasi pasien akan tersimpan tanpa
            // klinik dan hilang dari laporan per klinik.
            $clinicId = $data['clinic_id'] ?? null;
            if (! $clinicId) {
                $clinicId = \App\Models\User::whereKey($userId)->value('clinic_id');
            }
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
        ?int $doctorId = null,
        ?int $clinicId = null
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
            'clinic_id' => $clinicId,
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

    /**
     * Notifikasi antrian.
     *
     * Hanya dikirim ketika status antrian benar-benar berubah (pemanggil
     * memastikan itu). Isi sengaja pendek dan tidak memuat diagnosis.
     */
    public function sendQueueEvent(int $userId, string $event, Booking $booking, ?int $medicalRecordId = null): bool
    {
        $label = $booking->queue_number
            ? 'A-' . str_pad((string) $booking->queue_number, 2, '0', STR_PAD_LEFT)
            : null;
        $petName = $booking->pet?->name ?? 'hewan Anda';
        $doctorName = $booking->doctor?->name ?? 'dokter';
        $clinicName = $booking->clinic?->name;

        // Ruang pemeriksaan opsional. Tidak ada kolom ruang di tabel klinik,
        // jadi nilainya dibaca dari pengaturan (`queue.announcement_room`,
        // mis. "Ruang Pemeriksaan 2"). Bila belum diisi, aplikasi memakai
        // kalimat umum tanpa mengarang nomor ruang.
        $room = trim((string) AppSetting::getValue('queue.announcement_room', ''));

        // Identitas satu peristiwa antrian. Dipakai aplikasi untuk menjamin
        // satu panggilan hanya menghasilkan satu pengumuman suara, meski FCM
        // mengirim ulang pesan yang sama. `called_at` berubah setiap kali
        // dokter memanggil (termasuk panggil ulang), sehingga panggilan baru
        // tetap diumumkan.
        $eventId = $booking->id . ':' . $event;
        if ($event === 'called') {
            $eventId .= ':' . ($booking->called_at?->getTimestamp() ?? 'unknown');
        }

        [$title, $body] = match ($event) {
            'checked_in' => [
                'Check-in berhasil',
                trim('Nomor antrian Anda ' . ($label ?? '-') . '. Silakan menunggu panggilan dokter.'),
            ],
            'called' => [
                'Giliran pemeriksaan Anda',
                trim('Nomor antrian ' . ($label ?? '-') . ' dipanggil. Silakan menuju ruang dokter ' . $doctorName . '.'),
            ],
            'consultation_started' => [
                'Pemeriksaan dimulai',
                "Pemeriksaan {$petName} sedang berlangsung.",
            ],
            'consultation_completed' => [
                'Pemeriksaan selesai',
                "Pemeriksaan {$petName} selesai. Hasil rekam medis sudah tersedia di aplikasi.",
            ],
            default => ['PetHeal', 'Status antrian Anda diperbarui.'],
        };

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'queue_' . $event,
            'category' => 'queue',
            'title' => $title,
            'body' => $body,
            'queue_status' => (string) $booking->queue_status,
            'queue_number' => $booking->queue_number,
            'queue_label' => $label,
            'queue_event' => $event,
            'queue_event_id' => $eventId,
            'doctor_name' => $doctorName,
            'room' => $room !== '' ? $room : null,
            'booking_id' => $booking->id,
            'pet_id' => $booking->pet_id,
            'doctor_id' => $booking->doctor_id,
            'medical_record_id' => $medicalRecordId,
            'pet_name' => $petName,
            'clinic_name' => $clinicName,
            'clinic_id' => $booking->clinic_id,
            // Bukan data-only: bila aplikasi tidak terlihat (background atau
            // proses sudah dihentikan), sistem yang menampilkan notifikasi di
            // channel `petheal_queue`. Aplikasi tetap membangun tray sendiri
            // saat foreground, sehingga tidak pernah ada dua notifikasi.
        ], 'petheal_queue');
    }

    /**
     * Notifikasi permintaan layanan / penawaran.
     *
     * Catatan admin/dokter tidak dimasukkan ke judul/isi notifikasi — hanya
     * ditampilkan di aplikasi setelah pemilik membuka detailnya.
     */
    public function sendServiceRequestEvent(int $userId, string $event, ServiceRequest $request): bool
    {
        $petName = $request->pet?->name ?? 'hewan Anda';

        [$title, $body] = match ($event) {
            'submitted' => [
                'Permintaan layanan terkirim',
                "Permintaan layanan untuk {$petName} sudah kami terima. Klinik akan segera meninjaunya.",
            ],
            'under_review' => [
                'Permintaan layanan sedang ditinjau',
                "Klinik sedang meninjau permintaan layanan untuk {$petName}.",
            ],
            'info_required' => [
                'Klinik membutuhkan informasi tambahan',
                "Silakan buka aplikasi untuk melihat informasi yang dibutuhkan klinik untuk {$petName}.",
            ],
            'doctor_evaluated' => [
                'Evaluasi dokter selesai',
                "Dokter sudah mengevaluasi permintaan layanan untuk {$petName}. Klinik akan menyiapkan penawarannya.",
            ],
            'offer_created' => [
                'Penawaran layanan tersedia',
                "Klinik telah mengirimkan penawaran layanan untuk {$petName}. Silakan buka aplikasi untuk melihat rincian biaya dan jadwal.",
            ],
            'offer_updated' => [
                'Penawaran layanan diperbarui',
                "Klinik memperbarui penawaran layanan untuk {$petName}. Silakan periksa rincian terbarunya.",
            ],
            'offer_expiring' => [
                'Penawaran akan kedaluwarsa',
                "Penawaran layanan untuk {$petName} akan segera berakhir. Silakan tinjau sebelum kedaluwarsa.",
            ],
            'rejected' => [
                'Permintaan layanan ditolak',
                "Klinik belum dapat memenuhi permintaan layanan untuk {$petName}. Silakan buka aplikasi untuk melihat alasannya.",
            ],
            'converted' => [
                'Booking berhasil dibuat',
                "Penawaran disetujui dan booking untuk {$petName} sudah dibuat. Silakan lanjutkan pembayaran.",
            ],
            'expired' => [
                'Penawaran kedaluwarsa',
                "Penawaran layanan untuk {$petName} sudah kedaluwarsa. Klinik perlu mengirim penawaran baru.",
            ],
            default => ['PetHeal', 'Status permintaan layanan Anda diperbarui.'],
        };

        return $this->sendToUser($userId, $title, $body, [
            'type' => 'service_request',
            'category' => 'service_request',
            'service_request_event' => $event,
            'service_request_id' => $request->id,
            'status' => (string) $request->status,
            'pet_id' => $request->pet_id,
            'clinic_name' => $request->clinic?->name,
            'pet_name' => $petName,
        ], 'petheal_services');
    }
}
