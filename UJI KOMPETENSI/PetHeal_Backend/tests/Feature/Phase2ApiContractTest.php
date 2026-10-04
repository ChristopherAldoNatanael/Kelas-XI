<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
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
 * PHASE 2 (contract freeze): canonical API contract tests.
 *
 * Asserts envelope + status + required fields + pagination structure +
 * validation error structure + tenant isolation, with two tenants.
 * All fixtures roll back (DatabaseTransactions) — dev DB stays clean.
 */
class Phase2ApiContractTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $userA;
    private User $userB;
    private Doctor $doctorA;
    private Service $serviceA;
    private Pet $petA;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Contract A ' . $this->suffix,
            'slug' => 'contract-a-' . $this->suffix,
            'address' => 'Jl. Contract A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Contract B ' . $this->suffix,
            'slug' => 'contract-b-' . $this->suffix,
            'address' => 'Jl. Contract B',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, ?int $clinicId, string $role = 'user') {
            return User::create([
                'name' => $tag, 'email' => $tag . $this->suffix . '@contract.test',
                'password' => Hash::make('password123'),
                'role' => $role, 'clinic_id' => $clinicId,
            ]);
        };
        $this->userA = $mkUser('cusera', $this->clinicA->id);
        $this->userB = $mkUser('cuserb', $this->clinicB->id);

        $this->doctorA = Doctor::create([
            'name' => 'Dr Contract A', 'specialization' => 'Umum',
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
        ]);
        $this->serviceA = Service::create([
            'name' => 'Layanan Contract ' . $this->suffix, 'price' => 75000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
        $this->petA = Pet::create([
            'user_id' => $this->userA->id, 'name' => 'ContractPet',
            'species' => 'Cat', 'gender' => 'male',
        ]);
    }

    private function apiAs(User $user, array $headers = [])
    {
        auth()->forgetGuards();
        $token = $user->createToken('phase2-test')->plainTextToken;

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], $headers));
    }

    // ---------- envelope: auth ----------

    public function test_login_envelope_and_required_fields(): void
    {
        $res = $this->postJson('/api/auth/login', [
            'email' => $this->userA->email, 'password' => 'password123',
        ])->assertOk();

        $res->assertJsonStructure([
            'success', 'message',
            'data' => ['token', 'user' => ['id', 'name', 'email', 'role']],
        ]);
        $this->assertTrue($res->json('success'));
        $this->assertNotEmpty($res->json('data.token'));
    }

    public function test_profile_envelope(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/auth/profile')->assertOk();
        $res->assertJsonStructure(['success', 'data' => ['id', 'email']]);
        $this->assertTrue($res->json('success'));
    }

    public function test_logout_message_only_envelope(): void
    {
        $res = $this->apiAs($this->userA)->postJson('/api/auth/logout')->assertOk();
        $res->assertJsonStructure(['success', 'message']);
        $this->assertTrue($res->json('success'));
        $this->assertArrayNotHasKey('data', $res->json());
    }

    // ---------- 401 canonical ----------

    public function test_401_canonical_without_token(): void
    {
        $res = $this->getJson('/api/auth/profile')->assertStatus(401);
        $this->assertFalse($res->json('success'));
        $this->assertEquals('Unauthenticated.', $res->json('message'));
    }

    public function test_401_canonical_with_invalid_token(): void
    {
        $res = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-value',
            'Accept' => 'application/json',
        ])->getJson('/api/auth/profile')->assertStatus(401);
        $this->assertFalse($res->json('success'));
        $this->assertEquals('Unauthenticated.', $res->json('message'));
    }

    // ---------- 404 canonical ----------

    public function test_404_manual_envelope(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/doctors/999999999')->assertStatus(404);
        $this->assertFalse($res->json('success'));
        $this->assertNotEmpty($res->json('message'));
    }

    public function test_404_framework_envelope_via_find_or_fail(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/pets/999999999/weight-history')
            ->assertStatus(404);
        $this->assertFalse($res->json('success'));
        $this->assertNotEmpty($res->json('message'));
    }

    // ---------- 422 canonical validation envelope ----------

    public function test_422_validation_envelope(): void
    {
        $res = $this->apiAs($this->userA)->postJson('/api/bookings', [
            'booking_date' => 'not-a-date',
        ])->assertStatus(422);

        $res->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($res->json('success'));
        $this->assertNotEmpty($res->json('errors'));
    }

    public function test_422_cross_tenant_id_fails_validation_with_envelope(): void
    {
        $doctorB = Doctor::create([
            'name' => 'Dr Contract B', 'specialization' => 'Umum',
            'is_active' => true, 'clinic_id' => $this->clinicB->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
        ]);

        $res = $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $doctorB->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
        ])->assertStatus(422);

        $res->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($res->json('success'));
    }

    // ---------- pagination canonical ----------

    public function test_pets_pagination_canonical(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/pets')->assertOk();
        $res->assertJsonStructure([
            'success', 'data',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertIsArray($res->json('data'));
    }

    public function test_bookings_pagination_canonical(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/bookings')->assertOk();
        $res->assertJsonStructure([
            'success', 'data',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertIsArray($res->json('data'));
    }

    public function test_medical_records_pagination_canonical(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/medical-records')->assertOk();
        $res->assertJsonStructure([
            'success', 'data',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertIsArray($res->json('data'));
    }

    public function test_reviews_pagination_present_and_additive(): void
    {
        $res = $this->apiAs($this->userA)
            ->getJson('/api/doctors/' . $this->doctorA->id . '/reviews')->assertOk();
        $res->assertJsonStructure([
            'success',
            'data' => ['reviews', 'average_rating', 'total_reviews'],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_nested_histories_keep_documented_shape(): void
    {
        $w = $this->apiAs($this->userA)
            ->getJson('/api/pets/' . $this->petA->id . '/weight-history')->assertOk();
        $w->assertJsonStructure([
            'success',
            'data' => ['pet_id', 'records', 'pagination'],
        ]);

        $v = $this->apiAs($this->userA)
            ->getJson('/api/pets/' . $this->petA->id . '/vaccinations')->assertOk();
        $v->assertJsonStructure([
            'success',
            'data' => ['pet_id', 'vaccinations', 'pagination'],
        ]);
    }

    // ---------- single-resource + null contract ----------

    public function test_booking_medical_record_null_contract(): void
    {
        $booking = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '10:00', 'status' => 'pending',
            'payment_status' => 'pending', 'clinic_id' => $this->clinicA->id,
        ]);

        $res = $this->apiAs($this->userA)
            ->getJson('/api/bookings/' . $booking->id . '/medical-record')->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertNull($res->json('data'));
    }

    public function test_create_booking_returns_201_with_resource(): void
    {
        $res = $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '11:00',
        ])->assertStatus(201);

        $res->assertJsonStructure([
            'success', 'message',
            'data' => ['id', 'status', 'clinic_id'],
        ]);
        $this->assertEquals($this->clinicA->id, $res->json('data.clinic_id'));
    }

    // ---------- notifications / services contract ----------

    public function test_notifications_limit_contract(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/notifications?limit=50')->assertOk();
        $res->assertJsonStructure([
            'success', 'message',
            'data' => ['notifications', 'unread_count'],
        ]);
        $this->assertIsArray($res->json('data.notifications'));
    }

    public function test_services_public_slug_contract(): void
    {
        $this->getJson('/api/services')->assertStatus(422)
            ->assertJsonStructure(['success', 'message']);

        $res = $this->getJson('/api/services?clinic_slug=' . $this->clinicA->slug)->assertOk();
        $res->assertJsonStructure(['success', 'data']);
        foreach ($res->json('data') as $svc) {
            $this->assertArrayHasKey('id', $svc);
            $this->assertArrayHasKey('price', $svc);
        }
    }

    public function test_payment_snap_token_validation_envelope(): void
    {
        $res = $this->apiAs($this->userA)->postJson('/api/payment/snap-token', [
            'transaction_details' => ['order_id' => 'BOGUS', 'gross_amount' => 0],
        ])->assertStatus(422);
        $res->assertJsonStructure(['success', 'message', 'errors']);
        $this->assertFalse($res->json('success'));
    }
}
