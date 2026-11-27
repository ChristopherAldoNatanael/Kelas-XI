<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ClinicalEncounter;
use App\Models\MedicalRecord;
use App\Models\TreatmentConsent;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use App\Services\RecordLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.9 — UI pendaftaran walk-in dan darurat dari web admin.
 */
class P47WalkInEmergencyUiTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private Doctor $doctor;
    private User $owner;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P47 ' . $suffix,
            'slug' => 'p47-' . $suffix,
            'address' => 'Jl. P47',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin P47',
            'email' => 'admin' . $suffix . '@p47.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $doctorUser = User::create([
            'name' => 'Dokter P47',
            'email' => 'doctor' . $suffix . '@p47.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'name' => 'Dr P47',
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->owner = User::create([
            'name' => 'Owner P47',
            'email' => 'owner' . $suffix . '@p47.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'P47Pet',
            'species' => 'Cat',
            'gender' => 'female',
        ]);
    }

    public function test_admin_can_open_walk_in_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.walk-ins.index'))
            ->assertOk()
            ->assertSee('Pendaftaran Walk-in & Darurat')
            ->assertSee($this->doctor->name);
    }

    public function test_owner_autocomplete_returns_owner_and_pets(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.walk-ins.owners', ['q' => 'Owner P47']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->owner->id)
            ->assertJsonPath('data.0.pets.0.id', $this->pet->id)
            ->assertJsonPath('data.0.pets.0.name', 'P47Pet');

        $this->actingAs($this->admin)
            ->getJson(route('admin.walk-ins.owners', ['q' => 'O']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_existing_owner_walk_in_gets_queue_number(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.walk-ins.store'), [
                'visit_type' => 'walk_in',
                'owner_mode' => 'existing',
                'existing_owner_id' => $this->owner->id,
                'pet_id' => $this->pet->id,
                'doctor_id' => $this->doctor->id,
                'complaint' => 'Gatal sejak kemarin.',
            ])
            ->assertRedirect(route('admin.walk-ins.index'))
            ->assertSessionHas('success');

        $booking = Booking::where('user_id', $this->owner->id)->latest('id')->firstOrFail();

        $this->assertSame('walk_in', $booking->service_type);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('not_required', $booking->payment_status);
        $this->assertSame('waiting', $booking->queue_status);
        $this->assertNotNull($booking->queue_number);
        $this->assertSame(today()->toDateString(), $booking->queue_date?->toDateString());
        $this->assertNull($booking->triage_level);
        $this->assertStringContainsString('Gatal sejak kemarin.', (string) $booking->notes);
    }

    public function test_emergency_record_panel_updates_triage_consent_and_closes_encounter(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.walk-ins.store'), [
                'visit_type' => 'emergency',
                'owner_mode' => 'existing',
                'existing_owner_id' => $this->owner->id,
                'pet_id' => $this->pet->id,
                'doctor_id' => $this->doctor->id,
                'triage_level' => 'red',
                'complaint' => 'Kejang.',
            ])
            ->assertRedirect(route('admin.walk-ins.index'));

        $booking = Booking::where('user_id', $this->owner->id)->latest('id')->firstOrFail();

        $record = MedicalRecord::create([
            'booking_id' => null,
            'pet_id' => $booking->pet_id,
            'doctor_id' => $booking->doctor_id,
            'clinic_id' => $booking->clinic_id,
            'diagnosis' => 'Kejang',
            'treatment' => 'Stabilisasi',
            'status' => MedicalRecord::STATUS_DRAFT,
        ]);

        app(RecordLinkService::class)->linkBooking($record->fresh(), $booking);

        $record = $record->fresh(['encounter', 'booking']);

        $this->assertSame(ClinicalEncounter::TYPE_EMERGENCY, $record->encounter->type);
        $this->assertSame('red', $record->encounter->triage_level);

        $this->actingAs($this->admin)
            ->get(route('admin.medical-records.show', $record->id))
            ->assertOk()
            ->assertSee('Encounter Darurat')
            ->assertSee('Consent darurat');

        $this->actingAs($this->admin)
            ->post(route('admin.medical-records.emergency.triage', $record->id), [
                'triage_level' => 'yellow',
                'chief_complaint' => 'Kejang sudah mereda.',
            ])
            ->assertRedirect();

        $this->assertSame('yellow', $record->encounter->fresh()->triage_level);

        $this->actingAs($this->admin)
            ->post(route('admin.medical-records.emergency.consent', $record->id), [
                'emergency_basis' => 'life_threatening',
                'signer_name' => 'Petugas Jaga',
                'signer_relation' => 'petugas',
                'witness_name' => 'Saksi Klinik',
                'risks_explained' => 'Tindakan penyelamatan dilakukan sebelum consent tertulis.',
            ])
            ->assertRedirect();

        $this->assertTrue(
            TreatmentConsent::where('medical_record_id', $record->id)
                ->where('consent_type', TreatmentConsent::TYPE_DARURAT)
                ->where('emergency_basis', 'life_threatening')
                ->exists()
        );

        $this->actingAs($this->admin)
            ->post(route('admin.medical-records.emergency.close', $record->id), [
                'disposition' => 'stabil',
            ])
            ->assertRedirect();

        $encounter = $record->encounter->fresh();
        $this->assertSame(ClinicalEncounter::STATUS_COMPLETED, $encounter->status);
        $this->assertSame('stabil', $encounter->disposition);
    }

    public function test_new_owner_emergency_requires_triage_and_creates_pet(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.walk-ins.store'), [
                'visit_type' => 'emergency',
                'owner_mode' => 'new',
                'owner_name' => 'Pemilik Darurat',
                'owner_phone' => '08123456789',
                'pet_name' => 'Darurat Pet',
                'species' => 'Dog',
                'gender' => 'male',
                'doctor_id' => $this->doctor->id,
                'complaint' => 'Sesak napas.',
            ])
            ->assertSessionHasErrors('triage_level');

        $this->actingAs($this->admin)
            ->post(route('admin.walk-ins.store'), [
                'visit_type' => 'emergency',
                'owner_mode' => 'new',
                'owner_name' => 'Pemilik Darurat',
                'owner_phone' => '08123456789',
                'pet_name' => 'Darurat Pet',
                'species' => 'Dog',
                'gender' => 'male',
                'doctor_id' => $this->doctor->id,
                'triage_level' => 'red',
                'complaint' => 'Sesak napas.',
                'needs_payment' => '1',
                'amount' => 250000,
            ])
            ->assertRedirect(route('admin.walk-ins.index'))
            ->assertSessionHas('success');

        $owner = User::where('name', 'Pemilik Darurat')->firstOrFail();
        $pet = Pet::where('user_id', $owner->id)->where('name', 'Darurat Pet')->firstOrFail();
        $booking = Booking::where('user_id', $owner->id)->latest('id')->firstOrFail();

        $this->assertSame('emergency', $booking->service_type);
        $this->assertSame('red', $booking->triage_level);
        $this->assertSame($pet->id, $booking->pet_id);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame(250000.0, (float) $booking->total_amount);
        $this->assertSame('waiting', $booking->queue_status);
        $this->assertNotNull($booking->queue_number);
    }
}
