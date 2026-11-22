<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tindak lanjut notifikasi webhook yang tidak bisa diproses otomatis.
 *
 * Setiap payload Midtrans sudah disimpan di `payment_notifications` sejak
 * sebelum remediasi ini (PaymentService::logNotification), termasuk yang
 * signature-nya tidak valid atau order id-nya tidak dikenal. Masalahnya dulu
 * tidak ada satu layar pun yang menampilkan baris tersebut, sehingga \"webhook
 * hilang\" hanya bisa ditemukan dengan query manual ke database.
 *
 * Test ini mengunci alurnya: payload tersimpan -> muncul di halaman pengaturan
 * pembayaran -> admin menandainya sudah ditinjau (dengan jejak audit) -> hilang
 * dari daftar. Tidak ada perubahan status pembayaran dari aksi ini.
 */
class PaymentNotificationReviewTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $superAdmin;
    private User $adminA;
    private User $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Notif A ' . $this->suffix,
            'slug' => 'notif-a-' . $this->suffix,
            'address' => 'Jl. Notif A',
            'is_active' => true,
        ]);

        $this->clinicB = Clinic::create([
            'name' => 'Notif B ' . $this->suffix,
            'slug' => 'notif-b-' . $this->suffix,
            'address' => 'Jl. Notif B',
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'notifsuper' . $this->suffix,
            'email' => 'notifsuper' . $this->suffix . '@notif.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);

        $this->adminA = User::create([
            'name' => 'notifadmin' . $this->suffix,
            'email' => 'notifadmin' . $this->suffix . '@notif.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->patient = User::create([
            'name' => 'notifpatient' . $this->suffix,
            'email' => 'notifpatient' . $this->suffix . '@notif.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => null,
        ]);
    }

    public function test_webhook_with_unknown_order_is_retained_and_reviewable(): void
    {
        $orderId = 'UNKNOWN-' . $this->suffix;

        $this->postJson('/api/midtrans/webhook', [
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '50000.00',
            'signature_key' => 'tidak-valid',
            'payment_type' => 'qris',
        ])->assertStatus(403);

        $notification = PaymentNotification::where('provider_order_id', $orderId)->first();

        $this->assertNotNull($notification, 'Payload webhook yang ditolak harus tetap tersimpan.');
        $this->assertFalse($notification->signature_valid);
        $this->assertFalse($notification->processed);

        // Muncul di halaman pengaturan pembayaran untuk ditinjau.
        $this->actingAs($this->superAdmin)
            ->get('/admin/payment-settings')
            ->assertOk()
            ->assertSee($orderId);

        $this->actingAs($this->superAdmin)
            ->post('/admin/payment-settings/notifications/' . $notification->id . '/review', [
                'note' => 'Dikonfirmasi bukan transaksi kita',
            ])
            ->assertRedirect();

        $reviewed = $notification->fresh();
        $this->assertTrue($reviewed->processed);
        $this->assertStringContainsString('Ditinjau', (string) $reviewed->processing_note);

        $this->assertSame(
            1,
            AuditLog::where('action', 'payment_notification.reviewed')
                ->where('model_id', $reviewed->id)
                ->count()
        );

        // Setelah ditinjau, baris tidak lagi muncul sebagai pekerjaan terbuka.
        $this->actingAs($this->superAdmin)
            ->get('/admin/payment-settings')
            ->assertOk()
            ->assertDontSee($orderId);
    }

    public function test_clinic_admin_cannot_review_another_clinics_notification(): void
    {
        $payment = $this->makePayment($this->clinicB, 'SHOP-' . $this->suffix . '-1');

        $notification = PaymentNotification::create([
            'payment_id' => $payment->id,
            'provider' => 'midtrans',
            'provider_order_id' => $payment->provider_order_id,
            'signature_valid' => false,
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '10000.00',
            'payload' => ['order_id' => $payment->provider_order_id],
            'processed' => false,
        ]);

        $this->actingAs($this->adminA)
            ->post('/admin/payment-settings/notifications/' . $notification->id . '/review')
            ->assertNotFound();

        $this->assertFalse($notification->fresh()->processed);
        $this->assertSame(0, AuditLog::where('action', 'payment_notification.reviewed')->count());
    }

    private function makePayment(Clinic $clinic, string $orderId): Payment
    {
        $doctor = Doctor::create([
            'name' => 'Dr Notif ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $service = Service::create([
            'name' => 'Layanan Notif ' . $this->suffix,
            'price' => 10000,
            'is_active' => true,
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'NotifPet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $booking = Booking::create([
            'user_id' => $this->patient->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'clinic_id' => $clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00',
            'status' => 'pending',
            'payment_type' => 'full',
            'payment_status' => 'pending',
            'total_amount' => 10000,
            'paid_amount' => 0,
            'remaining_amount' => 10000,
        ]);

        return Payment::create([
            'payment_code' => Payment::generatePaymentCode(),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_CONSULTATION,
            'clinic_id' => $clinic->id,
            'user_id' => $this->patient->id,
            'payable_type' => Booking::class,
            'payable_id' => $booking->id,
            'provider_order_id' => $orderId,
            'merchant_id' => 'MERCHANT-' . $this->suffix,
            'environment' => 'sandbox',
            'gross_amount' => 10000,
            'currency' => 'IDR',
            'status' => Payment::STATUS_PENDING,
            'provider_status' => 'pending',
        ]);
    }
}
