<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRefund;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Refund manual.
 *
 * Yang dikunci: tidak bisa mengembalikan lebih dari yang dibayar, tidak bisa
 * mencatat refund untuk booking yang masih berjalan, refund mengurangi
 * pendapatan terkumpul, dan admin hanya bisa menyentuh kliniknya sendiri.
 */
class RefundTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $patient;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Ref A ' . $suffix, 'slug' => 'ref-a-' . $suffix,
            'address' => 'Jl. A', 'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Ref B ' . $suffix, 'slug' => 'ref-b-' . $suffix,
            'address' => 'Jl. B', 'is_active' => true,
        ]);

        $mk = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag, 'email' => $tag . $suffix . '@ref.test',
            'password' => Hash::make('password123'), 'role' => $role, 'clinic_id' => $clinicId,
        ]);

        $this->adminA = $mk('radmin', 'clinic_admin', $this->clinicA->id);
        $this->patient = $mk('rpatient', 'user', null);

        $doctor = Doctor::create([
            'name' => 'Dr Ref ' . $suffix, 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinicA->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        $service = Service::create([
            'name' => 'Layanan Ref ' . $suffix, 'price' => 100000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
        $pet = Pet::create([
            'user_id' => $this->patient->id, 'name' => 'RefPet', 'species' => 'Cat', 'gender' => 'male',
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->patient->id, 'pet_id' => $pet->id, 'doctor_id' => $doctor->id,
            'service_id' => $service->id, 'clinic_id' => $this->clinicA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'), 'booking_time' => '09:00:00',
            'status' => 'cancelled', 'payment_status' => 'paid',
            'total_amount' => 100000, 'paid_amount' => 100000, 'remaining_amount' => 0,
        ]);
    }

    private function refunds(): RefundService
    {
        return app(RefundService::class);
    }

    public function test_refund_records_and_reduces_refundable_amount(): void
    {
        $refund = $this->refunds()->record($this->booking, 60000, 'transfer', 'TRX-1', 'Batal sebagian');

        $this->assertSame(60000.0, (float) $refund->amount);
        $this->assertSame(60000.0, $this->refunds()->refundedTotal($this->booking));
        $this->assertSame(40000.0, $this->refunds()->refundableAmount($this->booking));
    }

    public function test_cannot_refund_more_than_paid(): void
    {
        $this->expectException(RuntimeException::class);

        $this->refunds()->record($this->booking, 150000, 'transfer', null, 'Coba lebih');
    }

    public function test_cannot_refund_twice_beyond_paid_total(): void
    {
        $this->refunds()->record($this->booking, 60000, 'transfer', null, 'Pertama');

        $this->expectException(RuntimeException::class);
        $this->refunds()->record($this->booking, 60000, 'transfer', null, 'Kedua melebihi');
    }

    public function test_second_refund_within_remaining_is_allowed(): void
    {
        $this->refunds()->record($this->booking, 60000, 'transfer', null, 'Pertama');
        $this->refunds()->record($this->booking, 40000, 'cash', null, 'Sisanya');

        $this->assertSame(100000.0, $this->refunds()->refundedTotal($this->booking));
        $this->assertSame(0.0, $this->refunds()->refundableAmount($this->booking));
    }

    public function test_cannot_refund_a_booking_that_is_still_running(): void
    {
        $this->booking->update(['status' => 'confirmed']);

        $this->expectException(RuntimeException::class);
        $this->refunds()->record($this->booking->fresh(), 10000, 'transfer', null, 'Masih jalan');
    }

    public function test_reason_is_required(): void
    {
        $this->expectException(RuntimeException::class);

        $this->refunds()->record($this->booking, 10000, 'transfer', null, '   ');
    }

    public function test_unpaid_booking_has_nothing_to_refund(): void
    {
        $this->booking->update(['paid_amount' => 0]);

        $this->expectException(RuntimeException::class);
        $this->refunds()->record($this->booking->fresh(), 10000, 'transfer', null, 'Belum bayar');
    }

    // ---------- Web admin ----------

    public function test_admin_can_record_refund_via_form(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 50000,
                'method' => 'transfer',
                'reference' => 'TRX-9',
                'reason' => 'Pasien membatalkan H-1',
            ])
            ->assertRedirect();

        $this->assertSame(1, BookingRefund::where('booking_id', $this->booking->id)->count());
        $this->assertSame(50000.0, $this->refunds()->refundedTotal($this->booking));
    }

    public function test_admin_cannot_refund_a_booking_from_another_clinic(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 1,
                'method' => 'transfer',
                'reason' => 'x',
            ]);

        // Booking ini milik klinik A, jadi admin A boleh. Uji isolasi:
        // pindahkan booking ke klinik B lalu coba lagi.
        $this->booking->update(['clinic_id' => $this->clinicB->id]);

        $this->actingAs($this->adminA)
            ->post('/admin/payments/' . $this->booking->id . '/refund', [
                'amount' => 1000,
                'method' => 'transfer',
                'reason' => 'coba klinik lain',
            ])
            ->assertNotFound();
    }

    public function test_refund_reduces_collected_revenue_in_stats(): void
    {
        // Bandingkan sebelum & sesudah agar tidak bergantung pada isi DB.
        $before = $this->collectedTotal();

        $this->refunds()->record($this->booking, 100000, 'transfer', null, 'Refund penuh');

        $this->assertSame($before - 100000.0, $this->collectedTotal());
    }

    /**
     * Regresi: dulu status `cancelled` dikeluarkan dari paid_amount LALU
     * refund dikurangkan lagi, sehingga booking yang dibatalkan dan direfund
     * penuh menyumbang angka negatif ke laporan.
     */
    public function test_fully_refunded_booking_contributes_zero_not_negative(): void
    {
        $this->refunds()->record($this->booking, 100000, 'transfer', null, 'Refund penuh');

        $this->assertSame(
            0.0,
            $this->collectedFor(Booking::whereKey($this->booking->id))
        );
    }

    private function collectedTotal(): float
    {
        return $this->collectedFor(Booking::query());
    }

    private function collectedFor($query): float
    {
        $controller = app(\App\Http\Controllers\Admin\PaymentController::class);
        $method = new \ReflectionMethod($controller, 'paymentStats');
        $method->setAccessible(true);

        return (float) $method->invoke($controller, $query)['total_collected'];
    }
}
