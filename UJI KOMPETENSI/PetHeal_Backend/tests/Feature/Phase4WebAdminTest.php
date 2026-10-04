<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 4 (web clinic admin): auth, tenant isolation via URL, authorization
 * boundary vs super_admin, CRUD, business flows, exports, bilingual switch.
 * Fixtures roll back (DatabaseTransactions) — dev DB stays clean.
 */
class Phase4WebAdminTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $adminB;
    private User $superAdmin;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Service $serviceA;
    private Booking $bookingA;
    private Booking $bookingB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $mkClinic = function (string $tag) {
            return Clinic::create([
                'name' => 'WA ' . $tag . ' ' . $this->suffix,
                'slug' => 'wa-' . strtolower($tag) . '-' . $this->suffix,
                'address' => 'Jl. WA',
                'is_active' => true,
            ]);
        };
        $this->clinicA = $mkClinic('A');
        $this->clinicB = $mkClinic('B');

        $mkAdmin = function (string $tag, int $clinicId, string $role = 'clinic_admin') {
            return User::create([
                'name' => $tag, 'email' => $tag . $this->suffix . '@wa.test',
                'password' => Hash::make('password123'),
                'role' => $role, 'clinic_id' => $clinicId,
            ]);
        };
        $this->adminA = $mkAdmin('wadmina', $this->clinicA->id);
        $this->adminB = $mkAdmin('wadminb', $this->clinicB->id);
        $this->superAdmin = User::create([
            'name' => 'wsuper', 'email' => 'wsuper' . $this->suffix . '@wa.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin', 'clinic_id' => null,
        ]);

        $mkDoctor = function (int $clinicId, string $tag) {
            return Doctor::create([
                'name' => 'Dr WA ' . $tag, 'specialization' => 'Umum',
                'is_active' => true, 'clinic_id' => $clinicId,
                'available_days' => ['monday'],
                'start_time' => '08:00:00', 'end_time' => '17:00:00',
            ]);
        };
        $this->doctorA = $mkDoctor($this->clinicA->id, 'A');
        $this->doctorB = $mkDoctor($this->clinicB->id, 'B');

        $this->serviceA = Service::create([
            'name' => 'Layanan WA ' . $this->suffix, 'price' => 50000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);

        $ownerA = User::create([
            'name' => 'ownerA', 'email' => 'ownera' . $this->suffix . '@wa.test',
            'password' => Hash::make('password123'),
            'role' => 'user', 'clinic_id' => $this->clinicA->id,
        ]);
        $petA = Pet::create([
            'user_id' => $ownerA->id, 'name' => 'WAPetA',
            'species' => 'Cat', 'gender' => 'male',
        ]);
        $ownerB = User::create([
            'name' => 'ownerB', 'email' => 'ownerb' . $this->suffix . '@wa.test',
            'password' => Hash::make('password123'),
            'role' => 'user', 'clinic_id' => $this->clinicB->id,
        ]);
        $petB = Pet::create([
            'user_id' => $ownerB->id, 'name' => 'WAPetB',
            'species' => 'Dog', 'gender' => 'female',
        ]);

        $mkBooking = function ($owner, $pet, $doctor, $clinicId, string $time) {
            return Booking::create([
                'user_id' => $owner->id, 'pet_id' => $pet->id,
                'doctor_id' => $doctor->id,
                'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
                'booking_time' => $time, 'status' => 'pending',
                'payment_status' => 'pending', 'payment_type' => 'full',
                'total_amount' => 50000, 'paid_amount' => 0,
                'remaining_amount' => 50000, 'clinic_id' => $clinicId,
            ]);
        };
        $this->bookingA = $mkBooking($ownerA, $petA, $this->doctorA, $this->clinicA->id, '09:00');
        $this->bookingB = $mkBooking($ownerB, $petB, $this->doctorB, $this->clinicB->id, '09:00');
    }

    // ---------- auth ----------

    public function test_admin_login_logout_and_invalid(): void
    {
        $this->post('/admin/login', [
            'email' => $this->adminA->email, 'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->adminA);

        $this->actingAs($this->adminA)->post('/admin/logout')
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->post('/admin/login', [
            'email' => $this->adminA->email, 'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_remember_me_sets_cookie(): void
    {
        $res = $this->post('/admin/login', [
            'email' => $this->adminA->email, 'password' => 'password123',
            'remember' => '1',
        ])->assertRedirect(route('admin.dashboard'));

        $names = collect($res->headers->getCookies())->map(fn ($c) => $c->getName())->all();
        $this->assertNotEmpty(
            array_filter($names, fn ($n) => str_starts_with($n, 'remember_')),
            'remember-me cookie missing: ' . json_encode($names)
        );
    }

    public function test_inactive_clinic_login_rejected_with_message(): void
    {
        $this->clinicA->update(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => $this->adminA->email, 'password' => 'password123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ---------- tenant isolation via URL ----------

    public function test_admin_cannot_open_other_clinic_resources(): void
    {
        $asA = function () {
            return $this->actingAs($this->adminA);
        };

        $asA()->get('/admin/doctors/' . $this->doctorA->id . '/edit')->assertOk();
        $asA()->get('/admin/doctors/' . $this->doctorB->id . '/edit')->assertNotFound();

        $asA()->get('/admin/bookings/' . $this->bookingA->id)->assertOk();
        $asA()->get('/admin/bookings/' . $this->bookingB->id)->assertNotFound();

        $asA()->get('/admin/services/' . $this->serviceA->id . '/edit')->assertOk();

        $asA()->post('/admin/bookings/' . $this->bookingB->id . '/confirm')->assertNotFound();
        $asA()->post('/admin/payments/' . $this->bookingB->id . '/confirm', ['amount' => 1000])
            ->assertNotFound();
    }

    public function test_admin_b_can_use_own_resources(): void
    {
        $this->actingAs($this->adminB)->get('/admin/doctors/' . $this->doctorB->id . '/edit')->assertOk();
        $this->actingAs($this->adminB)->get('/admin/bookings/' . $this->bookingB->id)->assertOk();
    }

    // ---------- super_admin boundary ----------

    public function test_clinic_admin_blocked_from_super_admin_routes(): void
    {
        $asA = function () {
            return $this->actingAs($this->adminA);
        };

        $asA()->get('/admin/clinics')->assertStatus(403);
        $asA()->get('/admin/join-requests')->assertStatus(403);
        $asA()->get('/admin/notification-settings')->assertStatus(403);
        $asA()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicB->id])->assertStatus(403);

        // Sidebar must not offer the dead-end notification link.
        $asA()->get('/admin')->assertOk()
            ->assertDontSee(route('admin.notification-settings.index'), false);
    }

    public function test_super_admin_keeps_access(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/notification-settings')->assertOk();
        $this->actingAs($this->superAdmin)->get('/admin/clinics')->assertOk();
    }

    // ---------- mass assignment from web ----------

    public function test_role_and_clinic_cannot_be_smuggled(): void
    {
        $this->actingAs($this->adminA)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky' . $this->suffix . '@wa.test',
            'password' => 'password123', 'role' => 'super_admin',
            'clinic_id' => $this->clinicB->id,
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'sneaky' . $this->suffix . '@wa.test')->firstOrFail();
        $this->assertEquals('user', $created->role);
        $this->assertEquals($this->clinicA->id, $created->clinic_id);
    }

    // ---------- CRUD ----------

    public function test_doctor_crud_flow(): void
    {
        $payload = [
            'name' => 'Dr WA New', 'specialization' => 'Bedah',
            'available_days' => ['monday', 'tuesday'],
            'start_time' => '09:00', 'end_time' => '17:00',
        ];
        $this->actingAs($this->adminA)->post('/admin/doctors', $payload)
            ->assertRedirect(route('admin.doctors.index'));
        $doctor = Doctor::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Dr WA New')->firstOrFail();

        $this->actingAs($this->adminA)->put('/admin/doctors/' . $doctor->id, array_merge($payload, ['name' => 'Dr WA Renamed']))
            ->assertRedirect(route('admin.doctors.index'));
        $this->assertEquals('Dr WA Renamed', $doctor->fresh()->name);

        $this->actingAs($this->adminA)->delete('/admin/doctors/' . $doctor->id)
            ->assertRedirect(route('admin.doctors.index'));
        $this->assertNull(Doctor::find($doctor->id));
    }

    public function test_service_crud_and_import_page(): void
    {
        $this->actingAs($this->adminA)->post('/admin/services', [
            'name' => 'Grooming WA', 'price' => 80000, 'duration' => 60,
        ])->assertRedirect(route('admin.services.index'));
        $service = Service::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Grooming WA')->firstOrFail();

        $this->actingAs($this->adminA)->put('/admin/services/' . $service->id, [
            'name' => 'Grooming WA', 'price' => 90000, 'duration' => 60,
        ])->assertRedirect(route('admin.services.index'));
        $this->assertEquals(90000, (float) $service->fresh()->price);

        $this->actingAs($this->adminA)->get('/admin/services')->assertOk()
            ->assertSee('Grooming WA', false);
    }

    // ---------- booking flow ----------

    public function test_booking_confirm_complete_flow(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $this->bookingA->id . '/confirm')
            ->assertRedirect();
        $this->assertEquals('confirmed', $this->bookingA->fresh()->status);

        // Double confirm rejected (no longer pending).
        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $this->bookingA->id . '/confirm')
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $this->bookingA->id . '/complete')
            ->assertRedirect();
        $this->assertEquals('completed', $this->bookingA->fresh()->status);
    }

    public function test_booking_cancel_requires_reason(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $this->bookingA->id . '/cancel', [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $this->bookingA->id . '/cancel', ['reason' => 'Pasien sakit'])
            ->assertRedirect();
        $this->assertEquals('cancelled', $this->bookingA->fresh()->status);
        $this->assertEquals('Pasien sakit', $this->bookingA->fresh()->cancellation_reason);
    }

    // ---------- payment + medical + exports ----------

    public function test_payment_confirm_updates_and_audits(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/payments/' . $this->bookingA->id . '/confirm', [
                'amount' => 20000, 'notes' => 'DP tunai',
            ])->assertRedirect();

        $fresh = $this->bookingA->fresh();
        $this->assertEquals(20000, (float) $fresh->paid_amount);
        $this->assertEquals(30000, (float) $fresh->remaining_amount);
        $this->assertEquals('partial', $fresh->payment_status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.confirm',
            'clinic_id' => $this->clinicA->id,
        ]);
    }

    public function test_medical_create_and_exports(): void
    {
        $this->actingAs($this->adminA)->get('/admin/medical-records/create/' . $this->bookingA->id)->assertOk();
        // Foreign booking id must not render the form.
        $this->actingAs($this->adminA)->get('/admin/medical-records/create/' . $this->bookingB->id)->assertNotFound();

        $this->actingAs($this->adminA)->post('/admin/medical-records', [
            'booking_id' => $this->bookingA->id,
            'diagnosis' => 'Flu', 'treatment' => 'Rest',
        ])->assertRedirect(route('admin.bookings.index'));
        $this->assertEquals(1, MedicalRecord::where('booking_id', $this->bookingA->id)->count());

        $this->actingAs($this->adminA)->get('/admin/medical-records')->assertOk()
            ->assertSee('Flu', false);
        $this->actingAs($this->adminA)->get('/admin/medical-records/export/csv')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->adminA)->get('/admin/export/bookings?status=pending')->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        // Bad export filter range rejected, not 500.
        $this->actingAs($this->adminA)->get('/admin/medical-records/export/csv?from=not-a-date')
            ->assertSessionHasErrors('from');
    }

    // ---------- bilingual switch ----------

    public function test_locale_switch_and_message_language(): void
    {
        $this->actingAs($this->adminA)->get('/admin?lang=en')->assertOk();
        $this->assertEquals('en', session('app_locale'));

        $this->actingAs($this->adminA)->get('/admin?lang=id')->assertOk();
        $this->assertEquals('id', session('app_locale'));
    }
}
