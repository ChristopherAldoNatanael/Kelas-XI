<?php

namespace Tests\Feature;

use App\Imports\ServicesImport;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\DeviceToken;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Notification;
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
 * PHASE 3 (stabilization): regression tests for verified fixes.
 * Fixtures roll back (DatabaseTransactions) — dev DB stays clean.
 */
class Phase3StabilizationTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $userA;
    private User $adminA;
    private User $superAdmin;
    private Doctor $doctorA;
    private Service $serviceA;
    private Pet $petA;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Stab A ' . $this->suffix,
            'slug' => 'stab-a-' . $this->suffix,
            'address' => 'Jl. Stab A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Stab B ' . $this->suffix,
            'slug' => 'stab-b-' . $this->suffix,
            'address' => 'Jl. Stab B',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, ?int $clinicId, string $role = 'user') {
            return User::create([
                'name' => $tag, 'email' => $tag . $this->suffix . '@stab.test',
                'password' => Hash::make('password123'),
                'role' => $role, 'clinic_id' => $clinicId,
            ]);
        };
        $this->userA = $mkUser('susera', $this->clinicA->id);
        $this->adminA = $mkUser('sadmina', $this->clinicA->id, 'clinic_admin');
        $this->superAdmin = $mkUser('ssuper', null, 'super_admin');

        $this->doctorA = Doctor::create([
            'name' => 'Dr Stab A', 'specialization' => 'Umum',
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
        ]);
        $this->serviceA = Service::create([
            'name' => 'Layanan Stab ' . $this->suffix, 'price' => 100000,
            'description' => 'Original description',
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
        $this->petA = Pet::create([
            'user_id' => $this->userA->id, 'name' => 'StabPet',
            'species' => 'Cat', 'gender' => 'male',
        ]);
    }

    private function apiAs(User $user, array $headers = [])
    {
        auth()->forgetGuards();
        $token = $user->createToken('phase3-test')->plainTextToken;

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], $headers));
    }

    private function tomorrow(): string
    {
        return Carbon::tomorrow()->format('Y-m-d');
    }

    // ---------- F-01: doctor update cannot smuggle clinic_id ----------

    public function test_doctor_update_ignores_clinic_id(): void
    {
        $this->actingAs($this->adminA)->put('/admin/doctors/' . $this->doctorA->id, [
            'name' => 'Dr Stab A Renamed',
            'specialization' => 'Umum',
            'available_days' => ['monday'],
            'start_time' => '08:00',
            'end_time' => '17:00',
            'clinic_id' => $this->clinicB->id,
        ])->assertRedirect(route('admin.doctors.index'));

        $this->assertEquals($this->clinicA->id, $this->doctorA->fresh()->clinic_id);
        $this->assertEquals('Dr Stab A Renamed', $this->doctorA->fresh()->name);
    }

    // ---------- F-02: notification settings super_admin only ----------

    public function test_notification_settings_forbidden_for_clinic_admin(): void
    {
        $this->actingAs($this->adminA)->get('/admin/notification-settings')->assertStatus(403);
        $this->actingAs($this->superAdmin)->get('/admin/notification-settings')->assertOk();
    }

    // ---------- F-03: inactive clinic enforcement ----------

    public function test_inactive_clinic_blocks_api_and_web(): void
    {
        $this->clinicA->update(['is_active' => false]);

        $this->apiAs($this->userA)->getJson('/api/doctors')->assertStatus(403);

        $this->actingAs($this->adminA)->get('/admin')->assertRedirect(route('admin.login'));

        $this->postJson('/api/auth/login', [
            'email' => $this->userA->email, 'password' => 'password123',
        ])->assertStatus(403);
    }

    // ---------- B1/B2: no downgrade of recorded money ----------

    public function test_paid_booking_ignores_late_pending_and_failed(): void
    {
        $svc = app(PaymentStatusService::class);
        $order = 'BOOKING-9-' . (int) (microtime(true) * 1000);

        $booking = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '09:00',
            'status' => 'confirmed', 'payment_status' => 'paid',
            'payment_type' => 'full', 'total_amount' => 100000,
            'paid_amount' => 100000, 'remaining_amount' => 0,
            'clinic_id' => $this->clinicA->id,
        ]);

        $r1 = $svc->applyTransactionStatus($booking, $order . '-p', 'pending', 'bank_transfer', 100000, []);
        $this->assertFalse($r1['updated']);
        $this->assertEquals('paid', $r1['booking']->payment_status);

        $r2 = $svc->applyTransactionStatus($booking, $order . '-e', 'expire', 'bank_transfer', 100000, []);
        $this->assertFalse($r2['updated']);
        $this->assertEquals('paid', $r2['booking']->payment_status);
    }

    public function test_medrec_with_paid_amount_ignores_pending(): void
    {
        $svc = app(PaymentStatusService::class);
        $order = 'MEDREC-9-' . (int) (microtime(true) * 1000);

        $booking = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '10:00',
            'status' => 'completed', 'payment_status' => 'paid',
            'payment_type' => 'full', 'total_amount' => 100000,
            'paid_amount' => 100000, 'remaining_amount' => 0,
            'clinic_id' => $this->clinicA->id,
        ]);
        $record = MedicalRecord::create([
            'booking_id' => $booking->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'X', 'treatment' => 'Y',
            'extra_payment_amount' => 50000, 'extra_payment_paid_amount' => 20000,
            'extra_payment_status' => 'partial',
        ]);

        $r = $svc->applyMedicalRecordTransactionStatus($record, $order, 'pending', 'qris', 30000, []);
        $this->assertFalse($r['updated']);
        $this->assertEquals('partial', $r['medical_record']->extra_payment_status);
        $this->assertEquals(20000, (float) $r['medical_record']->extra_payment_paid_amount);
    }

    // ---------- B4/B5: remaining semantics ----------

    public function test_full_booking_created_with_full_remaining(): void
    {
        $res = $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '11:00',
        ])->assertStatus(201);

        $booking = Booking::find($res->json('data.id'));
        $this->assertEquals(100000, (float) $booking->total_amount);
        $this->assertEquals(100000, (float) $booking->remaining_amount);
    }

    public function test_remaining_endpoint_gated(): void
    {
        // Fresh DP booking: nothing paid yet -> 400 (must do DP first).
        $fresh = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '12:00',
            'status' => 'pending', 'payment_status' => 'dp_pending',
            'payment_type' => 'dp', 'total_amount' => 100000,
            'dp_amount' => 50000, 'paid_amount' => 0, 'remaining_amount' => 50000,
            'clinic_id' => $this->clinicA->id,
        ]);
        $this->apiAs($this->userA)
            ->postJson('/api/payment/remaining/' . $fresh->id)
            ->assertStatus(400);

        // Cancelled booking with balance -> 400.
        $cancelled = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '13:00',
            'status' => 'cancelled', 'payment_status' => 'dp_paid',
            'payment_type' => 'dp', 'total_amount' => 100000,
            'dp_amount' => 50000, 'paid_amount' => 50000, 'remaining_amount' => 50000,
            'clinic_id' => $this->clinicA->id,
        ]);
        $this->apiAs($this->userA)
            ->postJson('/api/payment/remaining/' . $cancelled->id)
            ->assertStatus(400);
    }

    // ---------- B6: import preserves blanks ----------

    public function test_import_blank_cells_do_not_wipe(): void
    {
        $import = new ServicesImport('update', $this->clinicA->id);
        $import->collection(collect([
            [
                'name' => $this->serviceA->name,
                'description' => '',
                'price' => 150000,
                'duration' => '',
                'status' => 'active',
            ],
        ]));

        $fresh = $this->serviceA->fresh();
        $this->assertEquals('Original description', $fresh->description);
        $this->assertEquals(150000, (float) $fresh->price);
    }

    // ---------- B7: weight recompute ----------

    public function test_current_weight_follows_latest_record(): void
    {
        $this->apiAs($this->userA)->postJson('/api/pets/' . $this->petA->id . '/weight-records', [
            'weight' => 5.0, 'recorded_at' => Carbon::today()->format('Y-m-d'),
        ])->assertStatus(201);
        $this->assertEquals(5.0, (float) $this->petA->fresh()->weight);

        // Backdated entry must not corrupt current weight.
        $this->apiAs($this->userA)->postJson('/api/pets/' . $this->petA->id . '/weight-records', [
            'weight' => 3.0, 'recorded_at' => Carbon::today()->subDays(30)->format('Y-m-d'),
        ])->assertStatus(201);
        $this->assertEquals(5.0, (float) $this->petA->fresh()->weight);

        // Deleting the latest recomputes to the older one.
        $latest = $this->petA->weightRecords()->orderBy('recorded_at', 'desc')->first();
        $this->apiAs($this->userA)
            ->deleteJson('/api/pets/' . $this->petA->id . '/weight-records/' . $latest->id)
            ->assertOk();
        $this->assertEquals(3.0, (float) $this->petA->fresh()->weight);
    }

    // ---------- B13: vaccination dates ----------

    public function test_future_administered_date_rejected(): void
    {
        $this->apiAs($this->userA)->postJson('/api/pets/' . $this->petA->id . '/vaccinations', [
            'vaccine_name' => 'Rabies',
            'date_administered' => Carbon::tomorrow()->format('Y-m-d'),
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ---------- F-05: device token takeover ----------

    public function test_device_token_takeover_rejected(): void
    {
        $other = User::create([
            'name' => 'other', 'email' => 'other' . $this->suffix . '@stab.test',
            'password' => Hash::make('password123'),
            'role' => 'user', 'clinic_id' => $this->clinicB->id,
        ]);
        DeviceToken::create([
            'user_id' => $other->id, 'token' => 'shared-token-' . $this->suffix,
            'device_type' => 'android',
        ]);

        $this->apiAs($this->userA)->postJson('/api/device-token', [
            'token' => 'shared-token-' . $this->suffix, 'device_type' => 'android',
        ])->assertStatus(409);

        $this->assertEquals(
            $other->id,
            DeviceToken::where('token', 'shared-token-' . $this->suffix)->first()->user_id
        );
    }

    // ---------- J-06: notification clinic attribution ----------

    public function test_booking_notification_carries_clinic(): void
    {
        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '14:00',
        ])->assertStatus(201);

        $notif = Notification::where('user_id', $this->userA->id)
            ->orderBy('id', 'desc')->first();
        $this->assertNotNull($notif);
        $this->assertEquals($this->clinicA->id, $notif->clinic_id);
    }

    // ---------- B10/D-08: admin medical record guards ----------

    public function test_admin_medical_record_duplicate_and_cancelled_guarded(): void
    {
        $booking = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '15:00',
            'status' => 'confirmed', 'payment_status' => 'pending',
            'payment_type' => 'full', 'total_amount' => 100000,
            'paid_amount' => 0, 'remaining_amount' => 100000,
            'clinic_id' => $this->clinicA->id,
        ]);

        $payload = [
            'booking_id' => $booking->id,
            'diagnosis' => 'Flu', 'treatment' => 'Rest',
            'cost' => 100000, 'treatment_cost' => 0, 'medicine_cost' => 0,
        ];

        $this->actingAs($this->adminA)->post('/admin/medical-records', $payload)
            ->assertRedirect(route('admin.bookings.index'));
        $this->assertEquals('completed', $booking->fresh()->status);

        // Second record for the same booking -> error, still one row.
        $this->actingAs($this->adminA)->post('/admin/medical-records', $payload)
            ->assertSessionHas('error');
        $this->assertEquals(1, MedicalRecord::where('booking_id', $booking->id)->count());

        // Cancelled booking -> error, status untouched.
        $cancelled = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '16:00',
            'status' => 'cancelled', 'payment_status' => 'pending',
            'payment_type' => 'full', 'total_amount' => 100000,
            'paid_amount' => 0, 'remaining_amount' => 100000,
            'clinic_id' => $this->clinicA->id,
        ]);
        $payload['booking_id'] = $cancelled->id;
        $this->actingAs($this->adminA)->post('/admin/medical-records', $payload)
            ->assertSessionHas('error');
        $this->assertEquals('cancelled', $cancelled->fresh()->status);
        $this->assertEquals(0, MedicalRecord::where('booking_id', $cancelled->id)->count());
    }

    // ---------- B16: cancel reason null ----------

    public function test_api_cancel_without_reason_stores_null(): void
    {
        $booking = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(), 'booking_time' => '17:00',
            'status' => 'pending', 'payment_status' => 'pending',
            'payment_type' => 'full', 'total_amount' => 100000,
            'paid_amount' => 0, 'remaining_amount' => 100000,
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->apiAs($this->userA)->postJson('/api/bookings/' . $booking->id . '/cancel', [])
            ->assertOk();
        $this->assertNull($booking->fresh()->cancellation_reason);
    }

    // ---------- V-03: sort direction ----------

    public function test_admin_payments_invalid_direction_does_not_500(): void
    {
        $this->actingAs($this->adminA)->get('/admin/payments?sort=updated_at&direction=sideways')
            ->assertOk();
    }
}
