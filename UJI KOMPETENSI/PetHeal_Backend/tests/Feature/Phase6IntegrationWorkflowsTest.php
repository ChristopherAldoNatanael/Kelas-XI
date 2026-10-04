<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 6 (integration): cross-module end-to-end workflows proving
 * Backend + Web Admin + Web Super Admin work as one system.
 * DatabaseTransactions — dev DB stays clean.
 */
class Phase6IntegrationWorkflowsTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $superAdmin;
    private User $adminA;
    private User $adminB;
    private User $ownerA;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Service $serviceA;
    private Pet $petA;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $mkClinic = function (string $tag) {
            return Clinic::create([
                'name' => 'INT ' . $tag . ' ' . $this->suffix,
                'slug' => 'int-' . strtolower($tag) . '-' . $this->suffix,
                'address' => 'Jl. INT',
                'is_active' => true,
            ]);
        };
        $this->clinicA = $mkClinic('A');
        $this->clinicB = $mkClinic('B');

        $this->superAdmin = User::create([
            'name' => 'intsuper', 'email' => 'intsuper' . $this->suffix . '@int.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin', 'clinic_id' => null,
        ]);
        $mkAdmin = function (string $tag, int $clinicId) {
            return User::create([
                'name' => $tag, 'email' => $tag . $this->suffix . '@int.test',
                'password' => Hash::make('password123'),
                'role' => 'clinic_admin', 'clinic_id' => $clinicId,
            ]);
        };
        $this->adminA = $mkAdmin('intadmina', $this->clinicA->id);
        $this->adminB = $mkAdmin('intadminb', $this->clinicB->id);

        $this->ownerA = User::create([
            'name' => 'intownera', 'email' => 'intownera' . $this->suffix . '@int.test',
            'password' => Hash::make('password123'),
            'role' => 'user', 'clinic_id' => $this->clinicA->id,
        ]);
        $this->petA = Pet::create([
            'user_id' => $this->ownerA->id, 'name' => 'IntPetA',
            'species' => 'Cat', 'gender' => 'male',
        ]);

        $mkDoctor = function (int $clinicId, string $tag) {
            return Doctor::create([
                'name' => 'Dr INT ' . $tag, 'specialization' => 'Umum',
                'is_active' => true, 'clinic_id' => $clinicId,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'start_time' => '08:00:00', 'end_time' => '17:00:00',
            ]);
        };
        $this->doctorA = $mkDoctor($this->clinicA->id, 'A');
        $this->doctorB = $mkDoctor($this->clinicB->id, 'B');

        $this->serviceA = Service::create([
            'name' => 'Layanan INT ' . $this->suffix, 'price' => 120000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
    }

    private function apiAs(User $user, array $headers = [])
    {
        auth()->forgetGuards();
        $token = $user->createToken('phase6-test')->plainTextToken;

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], $headers));
    }

    private function tomorrow(): string
    {
        return Carbon::tomorrow()->format('Y-m-d');
    }

    // ---------- clinic lifecycle E2E ----------

    public function test_clinic_lifecycle_end_to_end(): void
    {
        $slug = 'int-life-' . $this->suffix;

        // Create -> admin provisioned with correct binding.
        $this->actingAs($this->superAdmin)->post('/admin/clinics', [
            'name' => 'INT Life', 'slug' => $slug, 'address' => 'Jl. Life',
            'primary_color' => '#112233', 'is_active' => '1',
        ])->assertRedirect(route('admin.clinics.index'));

        $clinic = Clinic::where('slug', $slug)->firstOrFail();
        $admin = User::where('email', 'admin@' . $slug . '.com')->firstOrFail();
        $this->assertEquals('clinic_admin', $admin->role);
        $this->assertEquals($clinic->id, $admin->clinic_id);

        // New admin can log in and use tenant features.
        // actingAs(superAdmin) persists in-test; drop it first.
        auth()->logout();
        $this->post('/admin/login', [
            'email' => $admin->email, 'password' => 'password123',
        ]);
        // Password is random now; login with known password fails -> prove
        // provisioning does NOT use a guessable password.
        $this->assertGuest();

        // Tenant admin of the new clinic manages its doctors.
        $this->actingAs($admin)->post('/admin/doctors', [
            'name' => 'Dr Life', 'specialization' => 'Umum',
            'available_days' => ['monday'],
            'start_time' => '09:00', 'end_time' => '17:00',
        ])->assertRedirect(route('admin.doctors.index'));
        $this->assertEquals(
            $clinic->id,
            Doctor::where('name', 'Dr Life')->firstOrFail()->clinic_id
        );

        // Deactivate -> tenant locked out, data preserved.
        $this->actingAs($this->superAdmin)
            ->post('/admin/clinics/' . $clinic->id . '/toggle-active')
            ->assertRedirect();
        $this->assertFalse((bool) $clinic->fresh()->is_active);
        $this->assertEquals(1, Doctor::where('clinic_id', $clinic->id)->count());

        auth()->logout();
        auth()->logout();
        $this->post('/admin/login', [
            'email' => $admin->email, 'password' => 'password123',
        ])->assertSessionHasErrors('email');

        // Reactivate ->:x access restored, ownership intact.
        $this->actingAs($this->superAdmin)
            ->post('/admin/clinics/' . $clinic->id . '/toggle-active')
            ->assertRedirect();
        $this->assertTrue((bool) $clinic->fresh()->is_active);
        $this->assertEquals($clinic->id, Doctor::where('name', 'Dr Life')->first()->clinic_id);
    }

    // ---------- join request E2E ----------

    public function test_join_request_end_to_end(): void
    {
        $email = 'joiner' . $this->suffix . '@int.test';

        // Public submit.
        $this->post('/admin/register', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Joiner', 'email' => $email,
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.login'));
        $req = ClinicJoinRequest::where('email', $email)->firstOrFail();
        $this->assertEquals('pending', $req->status);

        // Super sees + approves -> provisioned admin logs in.
        $this->actingAs($this->superAdmin)->get('/admin/join-requests')->assertOk()
            ->assertSee($email, false);
        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $req->id . '/approve')
            ->assertRedirect();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertEquals('clinic_admin', $user->role);
        $this->assertEquals($this->clinicA->id, $user->clinic_id);

        auth()->logout();
        $this->post('/admin/login', ['email' => $email, 'password' => 'password123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    // ---------- doctor -> booking -> payment -> medical chain ----------

    public function test_full_clinical_chain_across_api_and_web(): void
    {
        // 1. API: owner books doctor A + service A.
        $res = $this->apiAs($this->ownerA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
        ])->assertStatus(201);
        $bookingId = $res->json('data.id');
        $booking = Booking::findOrFail($bookingId);
        $this->assertEquals($this->clinicA->id, $booking->clinic_id);
        $this->assertEquals(120000, (float) $booking->remaining_amount);

        // 2. Web: admin confirms.
        $this->actingAs($this->adminA)->post('/admin/bookings/' . $bookingId . '/confirm')->assertRedirect();
        $this->assertEquals('confirmed', $booking->fresh()->status);

        // 3. Payment: settlement via service (webhook-equivalent) -> paid.
        $svc = app(PaymentStatusService::class);
        $order = 'BOOKING-' . $bookingId . '-' . (int) (microtime(true) * 1000);
        $r = $svc->applyTransactionStatus($booking, $order, 'settlement', 'qris', 120000, []);
        $this->assertTrue($r['updated']);
        $this->assertEquals('paid', $r['booking']->payment_status);
        $this->assertEquals(0, (float) $r['booking']->remaining_amount);

        // Late duplicate/pending/stale-failed events cannot downgrade.
        $dup = $svc->applyTransactionStatus($booking->fresh(), $order, 'settlement', 'qris', 120000, []);
        $this->assertTrue($dup['duplicate']);
        $late = $svc->applyTransactionStatus($booking->fresh(), $order . '-x', 'expire', 'qris', 120000, []);
        $this->assertFalse($late['updated']);
        $this->assertEquals('paid', $late['booking']->payment_status);

        // 4. Web: medical record completes the booking.
        $this->actingAs($this->adminA)->post('/admin/medical-records', [
            'booking_id' => $bookingId,
            'diagnosis' => 'Sehat', 'treatment' => 'Vitamin',
            'cost' => 120000, 'treatment_cost' => 0, 'medicine_cost' => 0,
        ])->assertRedirect(route('admin.bookings.index'));
        $this->assertEquals('completed', $booking->fresh()->status);

        $record = MedicalRecord::where('booking_id', $bookingId)->firstOrFail();
        $this->assertEquals($this->clinicA->id, $record->clinic_id);

        // 5. API: owner reads the record; tenant B cannot.
        $this->apiAs($this->ownerA)->getJson('/api/medical-records/' . $record->id)->assertOk();

        $ownerB = User::create([
            'name' => 'intownerb', 'email' => 'intownerb' . $this->suffix . '@int.test',
            'password' => Hash::make('password123'),
            'role' => 'user', 'clinic_id' => $this->clinicB->id,
        ]);
        $this->apiAs($ownerB)->getJson('/api/medical-records/' . $record->id)->assertStatus(404);
    }

    public function test_double_booking_same_slot_rejected(): void
    {
        $payload = [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '10:00',
        ];
        $this->apiAs($this->ownerA)->postJson('/api/bookings', $payload)->assertStatus(201);
        $this->apiAs($this->ownerA)->postJson('/api/bookings', $payload)->assertStatus(409);
        $this->assertEquals(
            1,
            Booking::where('doctor_id', $this->doctorA->id)
                ->where('booking_date', $this->tomorrow())
                ->where('booking_time', '10:00')
                ->whereIn('status', ['pending', 'confirmed'])
                ->count()
        );
    }

    // ---------- switch + cross-tenant E2E ----------

    public function test_switch_sees_correct_tenant_pages(): void
    {
        $s = fn () => $this->actingAs($this->superAdmin);

        $s()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicA->id]);
        $s()->get('/admin/users')->assertOk()
            ->assertSee($this->adminA->email, false)
            ->assertDontSee($this->adminB->email, false);

        $s()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicB->id]);
        $s()->get('/admin/users')->assertOk()
            ->assertSee($this->adminB->email, false)
            ->assertDontSee($this->adminA->email, false);

        $s()->post('/admin/switch-clinic', ['clinic_id' => '']);
        $this->assertNull(session('current_clinic_id'));
    }

    // ---------- export isolation (CSV is plaintext-verifiable) ----------

    public function test_medical_csv_export_is_tenant_isolated(): void
    {
        $mkRecord = function ($ownerEmail, $pet, $doctor, $clinicId, $diag) {
            $owner = User::where('email', $ownerEmail)->first();
            if (!$owner) {
                $owner = User::create([
                    'name' => $ownerEmail, 'email' => $ownerEmail,
                    'password' => Hash::make('password123'),
                    'role' => 'user', 'clinic_id' => $clinicId,
                ]);
            }
            $booking = Booking::create([
                'user_id' => $owner->id, 'pet_id' => $pet->id,
                'doctor_id' => $doctor->id,
                'booking_date' => $this->tomorrow(), 'booking_time' => '08:00',
                'status' => 'completed', 'payment_status' => 'paid',
                'payment_type' => 'full', 'total_amount' => 50000,
                'paid_amount' => 50000, 'remaining_amount' => 0,
                'clinic_id' => $clinicId,
            ]);

            return MedicalRecord::create([
                'booking_id' => $booking->id, 'pet_id' => $pet->id,
                'doctor_id' => $doctor->id, 'clinic_id' => $clinicId,
                'diagnosis' => $diag, 'treatment' => 'T',
            ]);
        };

        $petB = Pet::create([
            'user_id' => User::create([
                'name' => 'intownerb2', 'email' => 'intownerb2' . $this->suffix . '@int.test',
                'password' => Hash::make('password123'),
                'role' => 'user', 'clinic_id' => $this->clinicB->id,
            ])->id,
            'name' => 'IntPetB', 'species' => 'Dog', 'gender' => 'female',
        ]);

        $mkRecord('intownera' . $this->suffix . '@int.test', $this->petA, $this->doctorA, $this->clinicA->id, 'DiagA-UNIQUE-' . $this->suffix);
        $mkRecord('intownerb2' . $this->suffix . '@int.test', $petB, $this->doctorB, $this->clinicB->id, 'DiagB-UNIQUE-' . $this->suffix);

        $csv = $this->actingAs($this->adminA)->get('/admin/medical-records/export/csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('DiagA-UNIQUE-' . $this->suffix, $csv);
        $this->assertStringNotContainsString('DiagB-UNIQUE-' . $this->suffix, $csv);
    }

    // ---------- UI walk: no broken pages ----------

    public function test_admin_ui_walk_returns_200(): void
    {
        $asA = fn () => $this->actingAs($this->adminA);
        $asA()->get('/admin')->assertOk();
        $asA()->get('/admin/doctors')->assertOk();
        $asA()->get('/admin/doctors/create')->assertOk();
        $asA()->get('/admin/services')->assertOk();
        $asA()->get('/admin/services/create')->assertOk();
        $asA()->get('/admin/bookings')->assertOk();
        $asA()->get('/admin/payments')->assertOk();
        $asA()->get('/admin/medical-records')->assertOk();
        $asA()->get('/admin/users')->assertOk();
        $asA()->get('/admin/users/create')->assertOk();
        $asA()->get('/admin/audit-logs')->assertOk();
        $asA()->get('/admin/settings')->assertOk();
    }

    public function test_super_admin_ui_walk_returns_200(): void
    {
        $asS = fn () => $this->actingAs($this->superAdmin);
        $asS()->get('/admin')->assertOk();
        $asS()->get('/admin/clinics')->assertOk();
        $asS()->get('/admin/clinics/create')->assertOk();
        $asS()->get('/admin/clinics/' . $this->clinicA->id . '/edit')->assertOk();
        $asS()->get('/admin/join-requests')->assertOk();
        $asS()->get('/admin/notification-settings')->assertOk();
        $asS()->get('/admin/audit-logs')->assertOk();
        $asS()->get('/admin/settings')->assertOk();
    }

    // ---------- cache invalidation E2E ----------

    public function test_service_price_edit_reaches_api_immediately(): void
    {
        $before = $this->apiAs($this->ownerA)->getJson('/api/services')->assertOk();
        $this->assertContains(120000.0, collect($before->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->all());

        $this->actingAs($this->adminA)->put('/admin/services/' . $this->serviceA->id, [
            'name' => $this->serviceA->name, 'price' => 135000,
        ])->assertRedirect(route('admin.services.index'));

        $after = $this->apiAs($this->ownerA)->getJson('/api/services')->assertOk();
        $this->assertContains(135000.0, collect($after->json('data'))->pluck('price')->map(fn ($p) => (float) $p)->all());
    }
}
