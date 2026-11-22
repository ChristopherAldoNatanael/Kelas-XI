<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentStatusService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Siklus status bayar untuk booking yang dibatalkan.
 *
 * Sebelumnya pembatalan hanya menulis `status = cancelled`; `payment_status`
 * tetap `paid`/`dp_paid` sehingga uang yang seharusnya kembali tampil sebagai
 * pendapatan sah dan tidak pernah muncul sebagai pekerjaan refund di layar mana
 * pun. Test di sini mengunci satu aturan:
 *
 *   booking batal + uang masuk - refund tercatat  ->  refund_pending
 *   booking batal + refund tercatat >= uang masuk ->  refunded
 *
 * Aturan itu hanya boleh punya satu implementasi
 * (`Booking::syncRefundPaymentStatus`), dipakai oleh pembatalan admin, pembatalan
 * pasien, pencatatan refund, dan webhook yang datang setelah pembatalan.
 */
class BookingRefundLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private User $patient;
    private User $doctorUser;
    private Doctor $doctor;
    private Service $service;
    private Pet $pet;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $suffix = Str::lower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Refund Lifecycle ' . $suffix,
            'slug' => 'refund-life-' . $suffix,
            'address' => 'Jl. Refund No. 1',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'refundadmin' . $suffix,
            'email' => 'refundadmin' . $suffix . '@refundlife.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->patient = User::create([
            'name' => 'refundpatient' . $suffix,
            'email' => 'refundpatient' . $suffix . '@refundlife.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => null,
        ]);

        $this->doctorUser = User::create([
            'name' => 'refunddoctor' . $suffix,
            'email' => 'refunddoctor' . $suffix . '@refundlife.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr Refund ' . $suffix,
            'email' => $this->doctorUser->email,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->service = Service::create([
            'name' => 'Konsultasi Refund ' . $suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'RefundPet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $this->booking = $this->makeBooking();
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'clinic_id' => $this->clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_method' => 'QRIS',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ], $attributes));
    }

    private function refunds(): RefundService
    {
        return app(RefundService::class);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->createToken('refund-lifecycle')->plainTextToken,
            'Accept' => 'application/json',
        ]);
    }

    // ---------- Pembatalan ----------

    public function test_admin_cancellation_marks_paid_booking_as_refund_pending(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/bookings/' . $this->booking->id . '/cancel', ['reason' => 'Dokter sakit'])
            ->assertRedirect();

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(
            Booking::PAYMENT_REFUND_PENDING,
            $this->booking->fresh()->payment_status
        );
        $this->assertSame(
            1,
            AuditLog::where('action', 'booking.refund_due')
                ->where('model_id', $this->booking->id)
                ->count()
        );
    }

    public function test_patient_cancellation_returns_refund_due_and_marks_refund_pending(): void
    {
        $response = $this->apiAs($this->patient)
            ->postJson('/api/bookings/' . $this->booking->id . '/cancel', ['reason' => 'Berubah rencana'])
            ->assertOk()
            ->assertJsonPath('refund_due', 100000);

        $this->assertStringContainsString('pengembalian', $response->json('message'));
        $this->assertSame(
            Booking::PAYMENT_REFUND_PENDING,
            $this->booking->fresh()->payment_status
        );
    }

    public function test_doctor_cancellation_marks_refund_pending_too(): void
    {
        // Jalur pembatalan ketiga (portal dokter) harus berakhir pada status yang
        // sama dengan admin & pasien. Dulu jalur ini hanya menulis `status`.
        $this->actingAs($this->doctorUser)
            ->post('/doctor/bookings/' . $this->booking->id . '/cancel', ['reason' => 'Emergency'])
            ->assertRedirect();

        $this->assertSame('cancelled', $this->booking->fresh()->status);
        $this->assertSame(Booking::PAYMENT_REFUND_PENDING, $this->booking->fresh()->payment_status);
        $this->assertSame(
            1,
            AuditLog::where('action', 'booking.refund_due')
                ->where('model_id', $this->booking->id)
                ->count()
        );
    }

    public function test_unpaid_cancellation_does_not_create_refund_obligation(): void
    {
        $unpaid = $this->makeBooking([
            'payment_status' => 'pending',
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'booking_time' => '10:00:00',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/bookings/' . $unpaid->id . '/cancel', ['reason' => 'Tidak jadi'])
            ->assertRedirect();

        // Tidak ada uang masuk -> tidak ada kewajiban refund, status bayar tidak
        // boleh berubah menjadi refund_pending.
        $this->assertSame('pending', $unpaid->fresh()->payment_status);
        $this->assertSame(
            0,
            AuditLog::where('action', 'booking.refund_due')
                ->where('model_id', $unpaid->id)
                ->count()
        );
    }

    // ---------- Pencatatan refund ----------

    public function test_partial_refund_keeps_status_pending_and_full_refund_marks_refunded(): void
    {
        $this->booking->update(['status' => 'cancelled']);
        $this->booking->syncRefundPaymentStatus();
        $this->assertSame(Booking::PAYMENT_REFUND_PENDING, $this->booking->fresh()->payment_status);

        $this->refunds()->record($this->booking->fresh(), 40000, 'transfer', 'TRX-1', 'Refund sebagian');
        $this->assertSame(
            Booking::PAYMENT_REFUND_PENDING,
            $this->booking->fresh()->payment_status,
            'Refund sebagian belum menyelesaikan kewajiban refund.'
        );

        $this->refunds()->record($this->booking->fresh(), 60000, 'transfer', 'TRX-2', 'Refund sisa');
        $this->assertSame(Booking::PAYMENT_REFUNDED, $this->booking->fresh()->payment_status);
        $this->assertSame(0.0, $this->booking->fresh()->refundableAmount());
    }

    public function test_cancelled_booking_never_returns_to_paid_status_after_refund(): void
    {
        $this->booking->update(['status' => 'cancelled']);
        $this->booking->syncRefundPaymentStatus();
        $this->refunds()->record($this->booking->fresh(), 100000, 'transfer', null, 'Refund penuh');

        $this->assertSame(Booking::PAYMENT_REFUNDED, $this->booking->fresh()->payment_status);
        $this->assertSame(1, BookingRefund::where('booking_id', $this->booking->id)->count());
    }

    // ---------- Konfirmasi kas manual ----------

    public function test_manual_confirmation_is_rejected_for_cancelled_booking(): void
    {
        $this->booking->update([
            'status' => 'cancelled',
            'payment_status' => 'refund_pending',
            'paid_amount' => 50000,
            'remaining_amount' => 50000,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/payments/' . $this->booking->id . '/confirm', ['amount' => 50000])
            ->assertSessionHasErrors('amount');

        $fresh = $this->booking->fresh();
        $this->assertSame(50000.0, (float) $fresh->paid_amount);
        $this->assertSame(Booking::PAYMENT_REFUND_PENDING, $fresh->payment_status);
    }

    // ---------- Webhook setelah pembatalan ----------

    public function test_settlement_after_cancellation_becomes_refund_obligation(): void
    {
        $this->booking->update([
            'status' => 'cancelled',
            'payment_status' => 'pending',
            'paid_amount' => 0,
            'remaining_amount' => 100000,
        ]);

        $result = app(PaymentStatusService::class)->applyTransactionStatus(
            $this->booking->fresh(),
            'BOOKING-' . $this->booking->id . '-1',
            'settlement',
            'qris',
            100000,
            ['transaction_status' => 'settlement', 'fraud_status' => 'accept']
        );

        $this->assertTrue($result['updated']);
        $fresh = $this->booking->fresh();
        $this->assertSame(Booking::PAYMENT_REFUND_PENDING, $fresh->payment_status);
        $this->assertSame(0.0, (float) $fresh->remaining_amount, 'Booking batal tidak boleh punya sisa tagihan.');
        $this->assertSame(100000.0, (float) $fresh->paid_amount, 'Uang yang benar-benar masuk tetap harus tercatat.');
    }

    public function test_pending_event_cannot_downgrade_refund_obligation(): void
    {
        $this->booking->update([
            'status' => 'cancelled',
            'payment_status' => Booking::PAYMENT_REFUND_PENDING,
            'paid_amount' => 50000,
            'remaining_amount' => 0,
        ]);

        $result = app(PaymentStatusService::class)->applyTransactionStatus(
            $this->booking->fresh(),
            'BOOKING-' . $this->booking->id . '-2',
            'pending',
            'qris',
            50000,
            ['transaction_status' => 'pending']
        );

        $this->assertFalse($result['updated']);
        $this->assertSame(
            Booking::PAYMENT_REFUND_PENDING,
            $this->booking->fresh()->payment_status
        );
    }

    // ---------- Laporan admin ----------

    public function test_payment_stats_report_outstanding_refund_obligation(): void
    {
        $this->booking->update(['status' => 'cancelled']);
        $this->booking->syncRefundPaymentStatus();
        $this->refunds()->record($this->booking->fresh(), 30000, 'cash', null, 'Refund awal');

        $stats = $this->paymentStats(Booking::query());

        $this->assertSame(1, $stats['refund_due_count']);
        $this->assertSame(70000.0, $stats['refund_due_amount']);

        $this->refunds()->record($this->booking->fresh(), 70000, 'cash', null, 'Refund sisa');

        $stats = $this->paymentStats(Booking::query());
        $this->assertSame(0, $stats['refund_due_count']);
        $this->assertSame(0.0, $stats['refund_due_amount']);
    }

    public function test_refund_statuses_are_listed_and_labelled_in_admin_payments(): void
    {
        $this->booking->update(['status' => 'cancelled']);
        $this->booking->syncRefundPaymentStatus();

        $this->actingAs($this->admin)
            ->get('/admin/payments?status=refund_pending')
            ->assertOk()
            ->assertSee('INV-' . $this->booking->id, false);

        $this->assertSame(__('common.refund_pending'), \App\Support\AdminStatus::paymentLabel('refund_pending'));
        $this->assertSame(__('common.refunded'), \App\Support\AdminStatus::paymentLabel('refunded'));
    }

    /**
     * `paymentStats` adalah private; dipanggil lewat reflection agar test
     * mengunci angka yang benar-benar dipakai halaman, bukan salinan logika.
     */
    private function paymentStats($query): array
    {
        $controller = app(\App\Http\Controllers\Admin\PaymentController::class);
        $method = new \ReflectionMethod($controller, 'paymentStats');
        $method->setAccessible(true);

        return $method->invoke($controller, $query);
    }
}
