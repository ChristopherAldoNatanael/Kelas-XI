<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 3 (UI/UX Operational Depth): clinic-scoped schedule board, command
 * palette search, and invoice PDF. Fixtures roll back via DatabaseTransactions.
 */
class Phase3OperationalDepthTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $superAdmin;
    private Booking $bookingA;
    private Booking $bookingB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $mkClinic = fn (string $tag) => Clinic::create([
            'name' => 'OD ' . $tag . ' ' . $this->suffix,
            'slug' => 'od-' . strtolower($tag) . '-' . $this->suffix,
            'address' => 'Jl. Operational',
            'is_active' => true,
        ]);
        $this->clinicA = $mkClinic('A');
        $this->clinicB = $mkClinic('B');

        $this->adminA = User::create([
            'name' => 'odadmin' . $this->suffix,
            'email' => 'odadmin' . $this->suffix . '@od.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);
        $this->superAdmin = User::create([
            'name' => 'odsuper' . $this->suffix,
            'email' => 'odsuper' . $this->suffix . '@od.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);

        $mkDoctor = fn (int $clinicId, string $tag) => Doctor::create([
            'name' => 'Dr OD ' . $tag . ' ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinicId,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $doctorA = $mkDoctor($this->clinicA->id, 'A');
        $doctorB = $mkDoctor($this->clinicB->id, 'B');

        $ownerA = User::create([
            'name' => 'OD Owner A ' . $this->suffix,
            'email' => 'odownera' . $this->suffix . '@od.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);
        $ownerB = User::create([
            'name' => 'OD Owner B ' . $this->suffix,
            'email' => 'odownerb' . $this->suffix . '@od.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicB->id,
        ]);
        $petA = Pet::create([
            'user_id' => $ownerA->id,
            'name' => 'OD Pet A ' . $this->suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);
        $petB = Pet::create([
            'user_id' => $ownerB->id,
            'name' => 'OD Pet B ' . $this->suffix,
            'species' => 'Dog',
            'gender' => 'female',
        ]);

        $mkBooking = fn ($owner, $pet, $doctor, int $clinicId, string $time) => Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => $time,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_type' => 'full',
            'total_amount' => 50000,
            'paid_amount' => 0,
            'remaining_amount' => 50000,
            'clinic_id' => $clinicId,
        ]);
        $this->bookingA = $mkBooking($ownerA, $petA, $doctorA, $this->clinicA->id, '09:00');
        $this->bookingB = $mkBooking($ownerB, $petB, $doctorB, $this->clinicB->id, '10:00');
    }

    public function test_super_admin_without_clinic_gets_clinic_picker(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/schedule')
            ->assertOk()
            ->assertViewIs('admin.schedule.select_clinic')
            ->assertSee('Pilih klinik terlebih dahulu');
    }

    public function test_schedule_board_is_clinic_scoped(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['current_clinic_id' => $this->clinicA->id])
            ->get('/admin/schedule?date=' . Carbon::tomorrow()->format('Y-m-d'))
            ->assertOk()
            ->assertSee('Papan Jadwal')
            ->assertSee('OD Pet A ' . $this->suffix)
            ->assertDontSee('OD Pet B ' . $this->suffix);
    }

    public function test_command_palette_is_clinic_scoped(): void
    {
        $this->actingAs($this->adminA)
            ->getJson('/admin/search?q=OD Pet A ' . $this->suffix)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.pets');

        $this->actingAs($this->adminA)
            ->getJson('/admin/search?q=OD Pet B ' . $this->suffix)
            ->assertOk()
            ->assertJsonCount(0, 'data.pets')
            ->assertJsonCount(0, 'data.bookings');
    }

    public function test_invoice_index_and_pdf_are_clinic_scoped(): void
    {
        $this->actingAs($this->adminA)
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('Invoice & Transaksi', false)
            ->assertSee('INV-' . str_pad((string) $this->bookingA->id, 5, '0', STR_PAD_LEFT));

        $response = $this->actingAs($this->adminA)
            ->get(route('admin.payments.receipt', $this->bookingA->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->adminA)
            ->get(route('admin.payments.receipt', $this->bookingB->id))
            ->assertNotFound();
    }
}
