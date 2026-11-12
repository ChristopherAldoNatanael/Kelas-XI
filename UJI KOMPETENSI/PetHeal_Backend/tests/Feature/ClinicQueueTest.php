<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Antrian harian klinik.
 *
 * Yang dikunci: nomor unik per klinik/hari, urutan mengikuti check-in,
 * antrian terpisah dari status booking, no-show tidak memakai ulang nomor,
 * dan klinik lain tidak bisa menyentuh antrian ini.
 */
class ClinicQueueTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $patient;
    private Doctor $doctorA;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Antri A ' . $suffix, 'slug' => 'antri-a-' . $suffix,
            'address' => 'Jl. A', 'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Antri B ' . $suffix, 'slug' => 'antri-b-' . $suffix,
            'address' => 'Jl. B', 'is_active' => true,
        ]);

        $mk = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag, 'email' => $tag . $suffix . '@antri.test',
            'password' => Hash::make('password123'), 'role' => $role, 'clinic_id' => $clinicId,
        ]);

        $this->adminA = $mk('qadmin', 'clinic_admin', $this->clinicA->id);
        $this->patient = $mk('qpatient', 'user', null);

        $this->doctorA = Doctor::create([
            'name' => 'Dr Antri ' . $suffix, 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinicA->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id, 'name' => 'AntriPet', 'species' => 'Cat', 'gender' => 'male',
        ]);
    }

    private function service(): \App\Services\QueueService
    {
        return app(\App\Services\QueueService::class);
    }

    private function booking(array $overrides = []): Booking
    {
        $service = Service::firstOrCreate(
            ['clinic_id' => $this->clinicA->id, 'name' => 'Layanan Antri'],
            ['price' => 100000, 'is_active' => true]
        );

        return Booking::create(array_merge([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $service->id,
            'clinic_id' => $this->clinicA->id,
            'booking_date' => today()->toDateString(),
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
        ], $overrides));
    }

    // ---------- Nomor antrian ----------

    public function test_check_in_assigns_sequential_numbers_in_arrival_order(): void
    {
        $first = $this->service()->checkIn($this->booking());
        $second = $this->service()->checkIn($this->booking(['booking_time' => '08:00:00']));

        $this->assertSame(1, $first->queue_number);
        // Datang kedua meski jadwalnya lebih pagi → tetap nomor 2.
        $this->assertSame(2, $second->queue_number);
        $this->assertSame('waiting', $second->queue_status);
        $this->assertNotNull($second->checked_in_at);
    }

    public function test_check_in_is_idempotent(): void
    {
        $booking = $this->booking();
        $first = $this->service()->checkIn($booking);
        $second = $this->service()->checkIn($booking->fresh());

        $this->assertSame($first->queue_number, $second->queue_number);
        $this->assertSame(1, Booking::whereNotNull('queue_number')->count());
    }

    public function test_pending_booking_cannot_check_in(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->checkIn($this->booking(['status' => 'pending']));
    }

    public function test_cancelled_booking_cannot_check_in(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->checkIn($this->booking(['status' => 'cancelled']));
    }

    // ---------- Urutan & status ----------

    public function test_call_next_takes_the_lowest_waiting_number(): void
    {
        $this->service()->checkIn($this->booking());
        $second = $this->service()->checkIn($this->booking());

        $called = $this->service()->callNext($this->clinicA->id);

        $this->assertSame(1, $called->queue_number);
        $this->assertSame('called', $called->queue_status);
        $this->assertNotNull($called->called_at);
        $this->assertSame('waiting', $second->fresh()->queue_status);
    }

    public function test_call_next_returns_null_when_nobody_is_waiting(): void
    {
        $this->assertNull($this->service()->callNext($this->clinicA->id));
    }

    public function test_no_show_does_not_free_the_number(): void
    {
        $first = $this->service()->checkIn($this->booking());
        $this->service()->transition($first, 'no_show');

        $second = $this->service()->checkIn($this->booking());

        // Nomor 1 tetap terpakai; pasien berikutnya mendapat 2.
        $this->assertSame(2, $second->queue_number);
        $this->assertSame('no_show', $first->fresh()->queue_status);
    }

    public function test_queue_status_does_not_change_booking_status(): void
    {
        $booking = $this->service()->checkIn($this->booking());
        $this->service()->transition($booking, 'in_service');

        // Antrian dan administrasi adalah dua sumbu berbeda.
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_position_counts_people_ahead(): void
    {
        $first = $this->service()->checkIn($this->booking());
        $this->service()->checkIn($this->booking());
        $third = $this->service()->checkIn($this->booking());

        $this->assertSame(2, $this->service()->positionOf($third->fresh()));
        $this->assertSame(0, $this->service()->positionOf($first->fresh()));
    }

    public function test_finished_patient_has_no_position(): void
    {
        $booking = $this->service()->checkIn($this->booking());
        $this->service()->transition($booking, 'done');

        $this->assertNull($this->service()->positionOf($booking->fresh()));
    }

    // ---------- Isolasi klinik ----------

    public function test_numbers_are_per_clinic_not_global(): void
    {
        $this->service()->checkIn($this->booking());
        $this->service()->checkIn($this->booking());

        $otherClinicDoctor = Doctor::create([
            'name' => 'Dr B ' . Str::random(4), 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinicB->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        $otherService = Service::create([
            'clinic_id' => $this->clinicB->id, 'name' => 'Layanan B ' . Str::random(4),
            'price' => 100000, 'is_active' => true,
        ]);

        $bookingB = Booking::create([
            'user_id' => $this->patient->id, 'pet_id' => $this->pet->id,
            'doctor_id' => $otherClinicDoctor->id, 'service_id' => $otherService->id,
            'clinic_id' => $this->clinicB->id,
            'booking_date' => today()->toDateString(), 'booking_time' => '09:00:00',
            'status' => 'confirmed', 'payment_status' => 'unpaid',
            'total_amount' => 100000, 'paid_amount' => 0, 'remaining_amount' => 100000,
        ]);

        $checkedInB = $this->service()->checkIn($bookingB);

        // Klinik B mulai dari nomor 1 lagi.
        $this->assertSame(1, $checkedInB->queue_number);
    }

    // ---------- Web admin ----------

    public function test_admin_board_only_shows_own_clinic(): void
    {
        $this->service()->checkIn($this->booking());

        $response = $this->actingAs($this->adminA)->get('/admin/queue');
        $response->assertOk();
        $response->assertSee('AntriPet');
    }

    public function test_admin_can_check_in_from_board(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->adminA)
            ->post('/admin/queue/' . $booking->id . '/check-in')
            ->assertRedirect();

        $this->assertSame(1, $booking->fresh()->queue_number);
    }

    public function test_admin_cannot_touch_other_clinic_booking(): void
    {
        $doctorB = Doctor::create([
            'name' => 'Dr B2 ' . Str::random(4), 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinicB->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday'],
        ]);
        $serviceB = Service::create([
            'clinic_id' => $this->clinicB->id, 'name' => 'Svc B ' . Str::random(4),
            'price' => 1000, 'is_active' => true,
        ]);
        $bookingB = Booking::create([
            'user_id' => $this->patient->id, 'pet_id' => $this->pet->id,
            'doctor_id' => $doctorB->id,
            'service_id' => $serviceB->id, 'clinic_id' => $this->clinicB->id,
            'booking_date' => today()->toDateString(), 'booking_time' => '10:00:00',
            'status' => 'confirmed', 'payment_status' => 'unpaid',
            'total_amount' => 1000, 'paid_amount' => 0, 'remaining_amount' => 1000,
        ]);

        $this->actingAs($this->adminA)
            ->post('/admin/queue/' . $bookingB->id . '/check-in')
            ->assertNotFound();

        $this->assertNull($bookingB->fresh()->queue_number);
    }

    /**
     * Regresi: picker klinik memakai route yang tidak pernah ada
     * (`admin.clinic-context.switch`), sehingga super_admin tanpa klinik
     * mendapat 500 saat membuka menu Antrian.
     */
    public function test_super_admin_without_clinic_sees_picker_not_500(): void
    {
        $super = User::create([
            'name' => 'Super Queue',
            'email' => 'super-' . Str::random(6) . '@antri.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);

        $this->actingAs($super)
            ->get('/admin/queue')
            ->assertOk()
            ->assertSee($this->clinicA->name);
    }
}
