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
 * P0.1 characterization tests.
 *
 * These tests do not describe the target architecture. They pin the behaviour
 * that already exists in production so later refactors cannot silently change
 * the owner API contract, tenant isolation, or the one-booking-one-record rule.
 */
class P01CharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $ownerA;
    private User $ownerB;
    private User $adminA;
    private User $doctorUserA;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Pet $petA;
    private Pet $petB;
    private Booking $bookingA;
    private Booking $bookingB;
    private MedicalRecord $recordA;
    private MedicalRecord $recordB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'P01 A ' . $this->suffix,
            'slug' => 'p01-a-' . $this->suffix,
            'address' => 'Jl. P01 A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'P01 B ' . $this->suffix,
            'slug' => 'p01-b-' . $this->suffix,
            'address' => 'Jl. P01 B',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, int $clinicId, string $role = 'user') {
            return User::create([
                'name' => $tag . ' ' . $this->suffix,
                'email' => $tag . $this->suffix . '@p01.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $clinicId,
            ]);
        };

        $this->ownerA = $mkUser('owner-a', $this->clinicA->id);
        $this->ownerB = $mkUser('owner-b', $this->clinicB->id);
        $this->adminA = $mkUser('admin-a', $this->clinicA->id, 'clinic_admin');
        $this->doctorUserA = $mkUser('doctor-a', $this->clinicA->id, 'doctor');

        $this->doctorA = Doctor::create([
            'name' => 'Dr P01 A ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
            'user_id' => $this->doctorUserA->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->doctorB = Doctor::create([
            'name' => 'Dr P01 B ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinicB->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $serviceA = Service::create([
            'name' => 'Layanan P01 A ' . $this->suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
        ]);
        $serviceB = Service::create([
            'name' => 'Layanan P01 B ' . $this->suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinicB->id,
        ]);

        $this->petA = Pet::create([
            'user_id' => $this->ownerA->id,
            'name' => 'P01PetA',
            'species' => 'Cat',
            'gender' => 'male',
        ]);
        $this->petB = Pet::create([
            'user_id' => $this->ownerB->id,
            'name' => 'P01PetB',
            'species' => 'Dog',
            'gender' => 'female',
        ]);

        $this->bookingA = Booking::create([
            'user_id' => $this->ownerA->id,
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $serviceA->id,
            'clinic_id' => $this->clinicA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);
        $this->bookingB = Booking::create([
            'user_id' => $this->ownerB->id,
            'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id,
            'service_id' => $serviceB->id,
            'clinic_id' => $this->clinicB->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '10:00:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);

        $this->recordA = MedicalRecord::create([
            'booking_id' => $this->bookingA->id,
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'Otitis externa',
            'treatment' => 'Bersihkan telinga',
            'status' => MedicalRecord::STATUS_FINAL,
            'extra_payment_status' => 'not_required',
            'extra_payment_amount' => 0,
            'extra_payment_paid_amount' => 0,
        ]);
        $this->recordB = MedicalRecord::create([
            'booking_id' => $this->bookingB->id,
            'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id,
            'clinic_id' => $this->clinicB->id,
            'diagnosis' => 'Secret B',
            'treatment' => 'Secret B',
            'status' => MedicalRecord::STATUS_FINAL,
        ]);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('p01-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    public function test_owner_record_payload_keeps_the_legacy_android_contract(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('../docs/p0/android_fixtures/medical_record_owner_v1.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $response = $this->apiAs($this->ownerA)->getJson('/api/medical-records/' . $this->recordA->id);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame($fixture['top_level_keys'], array_keys($data));
        $this->assertSame($fixture['doctor_keys'], array_keys($data['doctor']));
        $this->assertSame($fixture['booking_keys'], array_keys($data['booking']));
        $this->assertSame($fixture['clinic_keys'], array_keys($data['clinic']));
        $this->assertSame($this->recordA->id, $data['id']);
        $this->assertSame($this->recordA->id, $data['record_id']);
        $this->assertSame(MedicalRecord::STATUS_FINAL, $data['status']);
    }

    public function test_owner_cannot_read_another_owners_record(): void
    {
        $this->apiAs($this->ownerA)
            ->getJson('/api/medical-records/' . $this->recordB->id)
            ->assertNotFound();

        $this->apiAs($this->ownerA)
            ->getJson('/api/pets/' . $this->petB->id . '/medical-records')
            ->assertNotFound();
    }

    public function test_admin_and_doctor_cannot_cross_clinic_boundaries(): void
    {
        $this->actingAs($this->adminA)
            ->get('/admin/medical-records/' . $this->recordB->id)
            ->assertNotFound();

        $this->actingAs($this->doctorUserA)
            ->get('/doctor/medical-records/' . $this->recordB->id)
            ->assertNotFound();
    }

    public function test_owner_api_reads_the_same_record_for_index_pet_and_booking(): void
    {
        $index = $this->apiAs($this->ownerA)->getJson('/api/medical-records');
        $index->assertOk();
        $this->assertContains($this->recordA->id, array_column($index->json('data'), 'id'));

        $byPet = $this->apiAs($this->ownerA)->getJson('/api/pets/' . $this->petA->id . '/medical-records');
        $byPet->assertOk();
        $this->assertSame($this->recordA->id, $byPet->json('data.0.id'));

        $byBooking = $this->apiAs($this->ownerA)->getJson('/api/bookings/' . $this->bookingA->id . '/medical-record');
        $byBooking->assertOk();
        $this->assertSame($this->recordA->id, $byBooking->json('data.id'));
    }

    public function test_one_booking_keeps_one_record_and_finalize_sets_hash(): void
    {
        $this->assertSame(
            1,
            MedicalRecord::where('booking_id', $this->bookingA->id)->count()
        );

        $this->recordA->finalize($this->adminA->id);

        $this->assertNotNull($this->recordA->fresh()->content_hash);
    }
}
