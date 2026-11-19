<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Pet;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\FCMService;
use App\Services\ServiceRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Notifikasi FCM: token, duplikasi, dan kebocoran data.
 *
 * Yang dikunci:
 *  1. Hanya token `active` yang dikirimi notifikasi.
 *  2. Token yang ditolak FCM (UNREGISTERED) otomatis dinonaktifkan.
 *  3. Event yang sama tidak menghasilkan notifikasi ganda.
 *  4. Notifikasi antrean tidak memuat diagnosis di isi pesan.
 */
class FcmNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::lower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Fcm ' . $suffix,
            'slug' => 'fcm-' . $suffix,
            'address' => 'Jl. FCM',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Pemilik FCM',
            'email' => 'fcm' . $suffix . '@notif.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
    }

    /**
     * Palsukan seluruh panggilan HTTP ke FCM: endpoint token selalu sukses,
     * endpoint kirim pesan dikembalikan sesuai skenario.
     */
    private function fakeFcm(int $sendStatus = 200, string $sendBody = '{}'): void
    {
        Http::fake(function ($request) use ($sendStatus, $sendBody) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'fake-token'], 200);
            }

            return Http::response(json_decode($sendBody, true) ?? [], $sendStatus);
        });
    }

    private function token(string $value, string $status = DeviceToken::STATUS_ACTIVE): DeviceToken
    {
        return DeviceToken::create([
            'user_id' => $this->user->id,
            'token' => $value,
            'device_type' => 'android',
            'status' => $status,
        ]);
    }

    /** Booking terkonfirmasi dengan antrian yang siap dipanggil. */
    private function queueBooking(int $number = 7): Booking
    {
        $doctor = \App\Models\Doctor::create([
            'name' => 'Dr Notif ' . Str::random(4),
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'available_days' => ['monday'],
        ]);

        $service = \App\Models\Service::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Layanan Notif',
            'price' => 100000,
            'is_active' => true,
        ]);

        return Booking::create([
            'user_id' => $this->user->id,
            'pet_id' => Pet::create([
                'user_id' => $this->user->id, 'name' => 'PetAntre',
                'species' => 'Cat', 'gender' => 'female',
            ])->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'clinic_id' => $this->clinic->id,
            'booking_date' => today()->toDateString(),
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
            'queue_number' => $number,
            'queue_date' => today()->toDateString(),
            'queue_status' => 'called',
        ]);
    }

    // ---------- Token ----------

    public function test_only_active_tokens_receive_notifications(): void
    {
        $this->fakeFcm();
        $this->token('token-aktif');
        $this->token('token-invalid', DeviceToken::STATUS_INVALID);

        $sent = app(FCMService::class)->sendToUser($this->user->id, 'Judul', 'Isi', ['type' => 'general']);

        $this->assertTrue($sent);

        Http::assertSentCount(2); // 1x ambil access token, 1x kirim pesan

        $sentTokens = collect(Http::recorded())->map(fn ($pair) => $pair[0]->data())
            ->filter(fn ($payload) => isset($payload['message']['token']))
            ->map(fn ($payload) => $payload['message']['token'])
            ->values();

        $this->assertSame(['token-aktif'], $sentTokens->all());
    }

    public function test_unregistered_token_is_marked_invalid_and_not_retried(): void
    {
        $this->fakeFcm(404, '{"error":{"status":"NOT_FOUND","details":[{"errorCode":"UNREGISTERED"}]}}');
        $deviceToken = $this->token('token-rusak');

        $sent = app(FCMService::class)->sendToUser($this->user->id, 'Judul', 'Isi', ['type' => 'general']);

        $this->assertFalse($sent);
        $this->assertSame(DeviceToken::STATUS_INVALID, $deviceToken->fresh()->status);
    }

    public function test_invalid_token_can_be_reactivated_on_reregistration(): void
    {
        $deviceToken = $this->token('token-lama', DeviceToken::STATUS_INVALID);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->user->createToken('fcm-test')->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->postJson('/api/device-token', [
                'token' => 'token-lama',
                'device_type' => 'android',
            ])
            ->assertStatus(200);

        $this->assertSame(DeviceToken::STATUS_ACTIVE, $deviceToken->fresh()->status);
    }

    // ---------- Duplikasi ----------

    public function test_same_service_request_status_does_not_send_duplicate_notification(): void
    {
        $this->fakeFcm();

        $service = app(ServiceRequestService::class);
        $pet = Pet::create([
            'user_id' => $this->user->id, 'name' => 'PetNotif',
            'species' => 'Dog', 'gender' => 'male',
        ]);

        $request = $service->submit($this->user, $pet, [
            'clinic_id' => $this->clinic->id,
            'need_type' => 'consultation',
            'complaint' => 'Hewan perlu pemeriksaan rutin di klinik.',
        ]);

        $before = Notification::where('user_id', $this->user->id)->count();

        // Admin review ulang saat status sudah sama → tidak ada push kedua.
        $service->advance($request->fresh(), ServiceRequest::STATUS_UNDER_REVIEW, [], 'under_review');
        $service->advance($request->fresh(), ServiceRequest::STATUS_UNDER_REVIEW, [], 'under_review');

        $after = Notification::where('user_id', $this->user->id)->count();

        $this->assertSame($before + 1, $after);
    }

    // ---------- Payload ----------

    public function test_queue_notification_body_has_no_diagnosis(): void
    {
        $this->fakeFcm();

        $booking = $this->queueBooking();

        app(FCMService::class)->sendQueueEvent($this->user->id, 'called', $booking);

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'queue_called')
            ->firstOrFail();

        $this->assertStringContainsString('A-07', $notification->body);
        $this->assertStringNotContainsString('diagnos', strtolower($notification->body));

        // Data tambahan untuk pengumuman suara di aplikasi. Tidak ada
        // diagnosis/keluhan: hanya nomor, dokter, dan ruang.
        $this->assertSame('called', $notification->data['queue_event']);
        $this->assertSame('A-07', $notification->data['queue_label']);
        $this->assertSame($booking->doctor->name, $notification->data['doctor_name']);
        $this->assertArrayHasKey('queue_event_id', $notification->data);
    }

    /**
     * Satu pemanggilan = satu id peristiwa. Pengiriman ulang FCM memakai id
     * yang sama (aplikasi mengabaikannya), sedangkan panggil ulang dokter
     * menghasilkan id baru sehingga tetap diumumkan sekali lagi.
     */
    public function test_queue_call_event_id_is_stable_on_retry_and_new_on_recall(): void
    {
        $this->fakeFcm();

        $booking = $this->queueBooking();
        $booking->forceFill(['called_at' => now()->subMinutes(5)])->save();

        $send = function () use ($booking): Notification {
            app(FCMService::class)->sendQueueEvent(
                $this->user->id,
                'called',
                $booking->fresh(['pet', 'doctor', 'clinic'])
            );

            return Notification::where('user_id', $this->user->id)
                ->where('type', 'queue_called')
                ->latest('id')
                ->firstOrFail();
        };

        $first = $send();
        $retry = $send();
        $this->assertSame(
            $first->data['queue_event_id'],
            $retry->data['queue_event_id'],
            'Retry FCM untuk panggilan yang sama harus memakai id yang sama.'
        );

        // Dokter memanggil ulang pasien yang sama beberapa menit kemudian.
        $booking->forceFill(['called_at' => now()])->save();
        $recall = $send();
        $this->assertNotSame(
            $retry->data['queue_event_id'],
            $recall->data['queue_event_id'],
            'Panggil ulang adalah peristiwa baru dan harus diumumkan lagi.'
        );
    }

    /**
     * Regresi: tombol "Panggil ulang" di halaman dokter tidak mengirim
     * notifikasi apa pun karena `QueueService::transition()` langsung
     * kembali ketika status tujuan sama dengan status sekarang. Akibatnya
     * pasien yang sudah `called` tidak pernah dipanggil lagi.
     */
    public function test_recall_from_doctor_queue_announces_the_call_again(): void
    {
        $this->fakeFcm();
        $this->token('token-recall');

        $booking = $this->queueBooking();
        $booking->forceFill([
            'queue_status' => \App\Services\QueueService::STATUS_WAITING,
            'called_at' => null,
        ])->save();

        $queue = app(\App\Services\QueueService::class);

        // Panggilan pertama: menunggu -> dipanggil.
        $queue->transition($booking->fresh(), \App\Services\QueueService::STATUS_CALLED);

        $first = Notification::where('user_id', $this->user->id)
            ->where('type', 'queue_called')
            ->latest('id')
            ->firstOrFail();
        $previousEventId = $first->data['queue_event_id'];

        // Dokter menekan "Panggil ulang": status tetap `called`, tetapi
        // pasien harus dipanggil (dan diumumkan) sekali lagi.
        $queue->transition($booking->fresh(), \App\Services\QueueService::STATUS_CALLED);

        $second = Notification::where('user_id', $this->user->id)
            ->where('type', 'queue_called')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($previousEventId, $second->data['queue_event_id']);
        $this->assertSame(
            2,
            Notification::where('user_id', $this->user->id)->where('type', 'queue_called')->count(),
            'Setiap panggil ulang harus menghasilkan satu notifikasi baru.'
        );
        $this->assertNotNull($booking->fresh()->called_at);
    }
}
