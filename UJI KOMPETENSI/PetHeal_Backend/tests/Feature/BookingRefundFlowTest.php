<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\PaymentRefund;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Alur pengembalian dana untuk booking yang dibatalkan sebelum pasien datang.
 *
 * Dua jalur yang dijaga test ini:
 *  1. `midtrans` — dana diminta kembali ke metode pembayaran ASAL lewat provider,
 *     jadi klinik tidak perlu tahu nomor rekening pasien.
 *  2. manual (transfer/tunai/e-wallet) — klinik mengirim sendiri; bukti transfer
 *     bisa diunggah dan pesan WhatsApp untuk meminta data rekening tersedia di
 *     halaman booking.
 *
 * Aturan uang tetap sama dengan RefundService: nominal tidak boleh melebihi dana
 * yang pernah masuk, dan refund hanya untuk booking yang sudah dibatalkan.
 */
class BookingRefundFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private User $patient;
    private Doctor $doctor;
    private Service $service;
    private Pet $pet;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
        $suffix = Str::lower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Refund Flow ' . $suffix,
            'slug' => 'refund-flow-' . $suffix,
            'address' => 'Jl. Refund Flow No. 1',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'refundflowadmin' . $suffix,
            'email' => 'refundflowadmin' . $suffix . '@refundflow.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->patient = User::create([
            'name' => 'Pasien Refund',
            'email' => 'refundflowpatient' . $suffix . '@refundflow.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'phone' => '081234567890',
        ]);

        $doctorUser = User::create([
            'name' => 'refundflowdoctor' . $suffix,
            'email' => 'refundflowdoctor' . $suffix . '@refundflow.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'name' => 'Dr Refund Flow ' . $suffix,
            'email' => $doctorUser->email,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->service = Service::create([
            'name' => 'Konsultasi Refund Flow ' . $suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'RefundFlowPet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'clinic_id' => $this->clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00',
            'status' => 'cancelled',
            'payment_type' => 'full',
            'payment_method' => 'QRIS',
            'payment_status' => Booking::PAYMENT_REFUND_PENDING,
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);
    }

    private function onlinePayment(int $gross = 100000, string $status = Payment::STATUS_PAID): Payment
    {
        return Payment::create([
            'payment_code' => 'PAY-' . Str::upper(Str::random(8)),
            'provider' => 'midtrans',
            'payment_type' => Payment::TYPE_CONSULTATION,
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->patient->id,
            'payable_type' => Booking::class,
            'payable_id' => $this->booking->id,
            'provider_order_id' => 'BOOK-' . $this->booking->id . '-' . Str::lower(Str::random(4)),
            'provider_transaction_id' => 'trx-' . Str::lower(Str::random(6)),
            'environment' => 'sandbox',
            'currency' => 'IDR',
            'payment_method' => 'qris',
            'status' => $status,
            'gross_amount' => $gross,
            'refunded_amount' => 0,
            'paid_at' => now(),
        ]);
    }

    // ---------- Tautan WhatsApp ----------

    public function test_whatsapp_link_normalizes_indonesian_numbers(): void
    {
        $this->assertSame('6281234567890', WhatsApp::normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', WhatsApp::normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', WhatsApp::normalize('81234567890'));
        $this->assertNull(WhatsApp::normalize('123'));
        $this->assertNull(WhatsApp::normalize(null));

        $link = WhatsApp::link('081234567890', "Halo & salam\nTerima kasih");
        $this->assertNotNull($link);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $link);
        // Baris baru dan `&` harus ter-encode supaya pesan tidak terpotong.
        $this->assertStringNotContainsString('&salam', (string) parse_url($link, PHP_URL_QUERY));
        $this->assertStringContainsString('%0A', $link);
    }

    // ---------- Jalur manual ----------

    public function test_manual_refund_can_store_transfer_proof(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 100000,
                'method' => 'transfer',
                'reference' => 'TRX-123',
                'reason' => 'Pasien membatalkan sebelum datang',
                'proof' => UploadedFile::fake()->image('bukti-transfer.png', 600, 800),
            ])
            ->assertRedirect();

        $refund = BookingRefund::where('booking_id', $this->booking->id)->firstOrFail();

        $this->assertSame('transfer', $refund->method);
        $this->assertNotNull($refund->proof_path);
        Storage::disk('public')->assertExists($refund->proof_path);
        $this->assertNotNull($refund->proof_url);

        // Uang yang sudah kembali membuat status bayar booking ikut berubah.
        $this->assertSame(Booking::PAYMENT_REFUNDED, $this->booking->fresh()->payment_status);
    }

    public function test_proof_must_be_an_image_or_pdf(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 50000,
                'method' => 'transfer',
                'reason' => 'Pembatalan',
                'proof' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('proof');

        $this->assertSame(0, BookingRefund::where('booking_id', $this->booking->id)->count());
    }

    public function test_refund_above_paid_amount_is_rejected_before_any_provider_call(): void
    {
        $this->onlinePayment();

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('requestRefund')->never();
        });

        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 150000,
                'method' => 'midtrans',
                'reason' => 'Terlalu besar',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, BookingRefund::where('booking_id', $this->booking->id)->count());
    }

    // ---------- Jalur Midtrans ----------

    public function test_midtrans_refund_records_provider_reference_and_booking_ledger(): void
    {
        $payment = $this->onlinePayment();

        $this->mock(PaymentService::class, function ($mock) use ($payment) {
            $mock->shouldReceive('requestRefund')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => null,
                    'refund' => new PaymentRefund([
                        'payment_id' => $payment->id,
                        'refund_code' => 'RFD-TEST-1',
                        'amount' => 100000,
                        'reason' => 'Pembatalan',
                        'status' => PaymentRefund::STATUS_PENDING,
                        'provider_refund_id' => 'refund-abc-123',
                    ]),
                ]);
        });

        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 100000,
                'method' => 'midtrans',
                'reason' => 'Pasien membatalkan sebelum datang',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = BookingRefund::where('booking_id', $this->booking->id)->firstOrFail();

        $this->assertSame('midtrans', $refund->method);
        $this->assertSame('refund-abc-123', $refund->provider_refund_id);
        $this->assertSame('RFD-TEST-1', $refund->reference);
        // Midtrans mengembalikan dana ke metode asal, jadi tidak ada bukti transfer.
        $this->assertNull($refund->proof_path);
        $this->assertSame(Booking::PAYMENT_REFUNDED, $this->booking->fresh()->payment_status);

        // Bukti audit menyebut nomor refund provider.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.refund',
            'model_id' => $this->booking->id,
        ]);
    }

    public function test_midtrans_refund_without_online_payment_tells_admin_to_use_manual(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 100000,
                'method' => 'midtrans',
                'reason' => 'Pembatalan',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, BookingRefund::where('booking_id', $this->booking->id)->count());
    }

    public function test_midtrans_rejection_does_not_record_a_refund(): void
    {
        $this->onlinePayment();

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('requestRefund')->once()->andReturn([
                'success' => false,
                'message' => 'Refund ditolak provider.',
                'refund' => null,
            ]);
        });

        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 100000,
                'method' => 'midtrans',
                'reason' => 'Pembatalan',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, BookingRefund::where('booking_id', $this->booking->id)->count());
        $this->assertSame(
            Booking::PAYMENT_REFUND_PENDING,
            $this->booking->fresh()->payment_status
        );
    }

    // ---------- Halaman booking ----------

    public function test_booking_page_offers_whatsapp_contact_and_midtrans_option(): void
    {
        $this->onlinePayment();

        $response = $this->actingAs($this->admin)->get('/admin/bookings/' . $this->booking->id);
        $response->assertOk();

        $response->assertSee('wa.me/6281234567890', false);
        $response->assertSee(__('admin.refund_wa_button'));
        $response->assertSee('value="midtrans"', false);
        // Pesan WA memuat nomor booking agar pasien yakin ini bukan penipuan.
        $response->assertSee('booking #' . $this->booking->id, false);
    }

    public function test_booking_page_hides_whatsapp_when_patient_has_no_phone(): void
    {
        $this->patient->forceFill(['phone' => null])->save();

        $response = $this->actingAs($this->admin)->get('/admin/bookings/' . $this->booking->id);
        $response->assertOk();

        $response->assertDontSee('wa.me/62', false);
        $response->assertSee(__('admin.refund_no_phone'));
    }

    public function test_booking_page_hides_midtrans_option_without_online_payment(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/bookings/' . $this->booking->id);
        $response->assertOk();

        $response->assertDontSee('value="midtrans"', false);
    }
}
