<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\RecordLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * P0.2 — encounter dan konsistensi relasi booking.
 */
class P02EncounterLinkTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $ownerA;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Pet $petA;
    private Booking $bookingA;
    private MedicalRecord $recordA;
    private RecordLinkService $links;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suffix = strtolower(Str::random(6));
        $this->links = app(RecordLinkService::class);

        $this->clinicA = Clinic::create([
            'name' => 'P02 A ' . $this->suffix,
            'slug' => 'p02-a-' . $this->suffix,
            'address' => 'Jl. P02 A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'P02 B ' . $this->suffix,
            'slug' => 'p02-b-' . $this->suffix,
            'address' => 'Jl. P02 B',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, int $clinicId, string $role = 'user') {
            return User::create([
                'name' => $tag . ' ' . $this->suffix,
                'email' => $tag . $this->suffix . '@p02.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $clinicId,
            ]);
        };

        $this->adminA = $mkUser('admin-a', $this->clinicA->id, 'clinic_admin');
        $this->ownerA = $mkUser('owner-a', $this->clinicA->id);

        $this->doctorA = Doctor::create([
            'name' => 'Dr P02 A ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->doctorB = Doctor::create([
            'name' => 'Dr P02 B ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinicB->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $serviceA = Service::create([
            'name' => 'Layanan P02 A ' . $this->suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->petA = Pet::create([
            'user_id' => $this->ownerA->id,
            'name' => 'P02PetA',
            'species' => 'Cat',
            'gender' => 'male',
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

        $this->recordA = MedicalRecord::create([
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'Otitis externa',
            'treatment' => 'Bersihkan telinga',
            'status' => MedicalRecord::STATUS_FINAL,
        ]);
    }

    public function test_link_booking_sets_all_three_relation_columns_and_is_idempotent(): void
    {
        $encounter = $this->links->linkBooking($this->recordA, $this->bookingA);

        $this->assertSame($this->clinicA->id, $encounter->clinic_id);
        $this->assertSame($this->petA->id, $encounter->pet_id);
        $this->assertSame($this->bookingA->id, $encounter->booking_id);
        $this->assertSame(ClinicalEncounter::TYPE_BOOKING, $encounter->type);

        $fresh = $this->recordA->fresh();
        $this->assertSame($encounter->id, $fresh->encounter_id);
        $this->assertSame($this->bookingA->id, $fresh->booking_id);
        $this->assertSame($fresh->id, $this->bookingA->fresh()->medical_record_id);

        $again = $this->links->linkBooking($fresh, $this->bookingA);

        $this->assertSame($encounter->id, $again->id);
        $this->assertSame(1, ClinicalEncounter::where('booking_id', $this->bookingA->id)->count());
    }

    public function test_cross_clinic_link_is_rejected_without_writing(): void
    {
        $foreignBooking = Booking::create([
            'user_id' => $this->ownerA->id,
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorB->id,
            'clinic_id' => $this->clinicB->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '11:00:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->links->linkBooking($this->recordA, $foreignBooking);
        } finally {
            $this->assertNull($this->recordA->fresh()->encounter_id);
            $this->assertNull($foreignBooking->fresh()->medical_record_id);
            $this->assertSame(0, ClinicalEncounter::where('booking_id', $foreignBooking->id)->count());
        }
    }

    public function test_walk_in_creates_encounter_without_booking(): void
    {
        $encounter = $this->links->openWithoutBooking(
            $this->recordA,
            ClinicalEncounter::TYPE_EMERGENCY,
            ['chief_complaint' => 'Kejang', 'triage_level' => 'red']
        );

        $this->assertNull($encounter->booking_id);
        $this->assertSame(ClinicalEncounter::TYPE_EMERGENCY, $encounter->type);
        $this->assertSame('Kejang', $encounter->chief_complaint);
        $this->assertNull($this->recordA->fresh()->booking_id);
        $this->assertSame($encounter->id, $this->recordA->fresh()->encounter_id);
    }

    public function test_backfill_links_legacy_record_and_check_links_detects_then_clears_drift(): void
    {
        // Rekam medis lama selalu punya booking_id; kolom encounter belum ada.
        $this->recordA->update(['booking_id' => $this->bookingA->id]);

        $this->artisan('medical-records:check-links')
            ->expectsOutputToContain('DRIFT record_without_encounter')
            ->assertFailed();

        $this->artisan('medical-records:backfill-encounters')
            ->assertSuccessful();

        $this->assertNotNull($this->recordA->fresh()->encounter_id);
        $this->assertSame($this->recordA->id, $this->bookingA->fresh()->medical_record_id);

        $this->artisan('medical-records:check-links')->assertSuccessful();
    }
}
