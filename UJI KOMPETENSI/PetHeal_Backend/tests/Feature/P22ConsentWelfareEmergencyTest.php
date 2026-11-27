<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\AnimalWelfareAssessment;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\TreatmentConsent;
use App\Models\User;
use App\Services\AnimalWelfareService;
use App\Services\ConsentService;
use App\Services\EmergencyEncounterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P2.2 — template consent, bukti, kesejahteraan berulang, dan darurat.
 */
class P22ConsentWelfareEmergencyTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $adminUser;
    private User $owner;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P22 ' . $suffix,
            'slug' => 'p22-' . $suffix,
            'address' => 'Jl. P22',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P22',
            'email' => 'doctor' . $suffix . '@p22.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin P22',
            'email' => 'admin' . $suffix . '@p22.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P22 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->owner = User::create([
            'name' => 'Owner P22',
            'email' => 'owner' . $suffix . '@p22.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'P22Pet',
            'species' => 'Cat',
            'gender' => 'female',
            'weight' => 4,
        ]);

        $encounter = ClinicalEncounter::create([
            'clinic_id' => $this->clinic->id,
            'pet_id' => $this->pet->id,
            'type' => ClinicalEncounter::TYPE_WALK_IN,
            'status' => ClinicalEncounter::STATUS_IN_PROGRESS,
            'attending_doctor_id' => $this->doctor->id,
            'opened_at' => now(),
            'created_by' => $this->doctorUser->id,
        ]);

        $this->record = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'encounter_id' => $encounter->id,
            'diagnosis' => 'Observasi',
            'treatment' => 'Pemeriksaan',
        ]);
    }

    public function test_consent_template_versions_are_immutable_and_consent_hash_is_recorded(): void
    {
        $service = app(ConsentService::class);

        $v1 = $service->publishTemplate([
            'template_key' => 'anestesi_umum',
            'name' => 'Anestesi Umum v1',
            'consent_type' => TreatmentConsent::TYPE_ANESTESI,
            'body' => 'Saya menyetujui anestesi umum versi 1.',
        ], $this->doctorUser);

        $v2 = $service->publishTemplate([
            'template_key' => 'anestesi_umum',
            'name' => 'Anestesi Umum v2',
            'consent_type' => TreatmentConsent::TYPE_ANESTESI,
            'body' => 'Saya menyetujui anestesi umum versi 2.',
        ], $this->doctorUser);

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);
        $this->assertFalse($v1->fresh()->is_current);
        $this->assertNull($v1->fresh()->active_key);
        $this->assertTrue($v2->fresh()->is_current);
        $this->assertSame(hash('sha256', $v2->body), $v2->body_hash);

        $consent = $service->createTreatmentConsent($this->record, [
            'consent_type' => TreatmentConsent::TYPE_ANESTESI,
            'template_key' => 'anestesi_umum',
            'signer_name' => 'Nyonya Pemilik',
            'signer_relation' => 'pemilik',
            'risks_explained' => 'Risiko reaksi anestesi dijelaskan.',
            'evidence_path' => 'consents/evidence-1.jpg',
            'evidence_hash' => str_repeat('a', 64),
        ], $this->doctorUser);

        $this->assertSame($this->clinic->id, $consent->clinic_id);
        $this->assertSame(2, $consent->template_version);
        $this->assertSame($v2->body_hash, $consent->consent_text_hash);
        $this->assertNotNull($consent->signed_at);
        $this->assertSame('consents/evidence-1.jpg', $consent->evidence_path);

        $revoked = $service->revoke($consent, 'Pemilik membatalkan tindakan.', $this->doctorUser);

        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame('Pemilik membatalkan tindakan.', $revoked->revoked_reason);
        $this->assertSame($this->doctorUser->id, $revoked->revoked_by);
        $this->assertSame('consents/evidence-1.jpg', $revoked->evidence_path);

        try {
            $service->revoke($revoked, 'Lagi.', $this->doctorUser);
            $this->fail('Pencabutan consent ganda seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CONSENT_ALREADY_REVOKED', $e->errorCode);
        }
    }

    public function test_welfare_can_be_assessed_repeatedly_and_scores_are_server_validated(): void
    {
        $service = app(AnimalWelfareService::class);

        $first = $service->assess($this->record, [
            'freedom_hunger_thirst' => 5,
            'freedom_discomfort' => 4,
            'freedom_pain_injury_disease' => 3,
            'freedom_fear_distress' => 4,
            'freedom_natural_behavior' => 5,
            'notes' => 'Kondisi awal.',
        ], $this->doctorUser);

        $second = $service->assess($this->record, [
            'freedom_hunger_thirst' => 4,
            'freedom_discomfort' => 3,
            'freedom_pain_injury_disease' => 2,
            'freedom_fear_distress' => 3,
            'freedom_natural_behavior' => 4,
            'notes' => 'Memburuk setelah rawat inap.',
        ], $this->doctorUser);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(21, $first->overall_score);
        $this->assertSame(16, $second->overall_score);
        $this->assertSame(4.2, $first->averageScore());
        $this->assertSame(2, $service->seriesForRecord($this->record)->count());
        $this->assertSame($second->id, $service->latestForRecord($this->record)->id);

        try {
            $service->assess($this->record, [
                'freedom_hunger_thirst' => 6,
                'freedom_discomfort' => 3,
                'freedom_pain_injury_disease' => 3,
                'freedom_fear_distress' => 3,
                'freedom_natural_behavior' => 3,
            ], $this->doctorUser);
            $this->fail('Skor di luar 1-5 seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('WELFARE_SCORE_INVALID', $e->errorCode);
        }
    }

    public function test_emergency_encounter_records_basis_without_granting_credentials(): void
    {
        $this->doctor->forceFill(['credential_status' => 'expired'])->save();

        $emergency = app(EmergencyEncounterService::class);
        $encounter = $emergency->open($this->clinic, $this->pet, [
            'triage_level' => EmergencyEncounterService::TRIAGE_RED,
            'chief_complaint' => 'Sesak napas berat.',
        ], $this->doctorUser);

        $this->assertSame(ClinicalEncounter::TYPE_EMERGENCY, $encounter->type);
        $this->assertSame(ClinicalEncounter::STATUS_IN_PROGRESS, $encounter->status);
        $this->assertNull($encounter->booking_id);
        $this->assertSame(EmergencyEncounterService::TRIAGE_RED, $encounter->triage_level);
        $this->assertSame('expired', $this->doctor->fresh()->credential_status);

        $service = app(ConsentService::class);

        try {
            $service->recordEmergencyConsent($this->record, [
                'signer_name' => 'Petugas Jaga',
                'emergency_basis' => '',
            ], $this->doctorUser);
            $this->fail('Consent darurat tanpa dasar seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EMERGENCY_BASIS_REQUIRED', $e->errorCode);
        }

        $consent = $service->recordEmergencyConsent($this->record, [
            'signer_name' => 'Petugas Jaga',
            'signer_relation' => 'petugas',
            'emergency_basis' => 'life_threatening',
            'risks_explained' => 'Tindakan penyelamatan nyawa dilakukan sebelum consent tertulis.',
            'evidence_path' => 'consents/emergency-1.jpg',
        ], $this->doctorUser);

        $this->assertSame(TreatmentConsent::TYPE_DARURAT, $consent->consent_type);
        $this->assertSame('life_threatening', $consent->emergency_basis);
        $this->assertTrue($consent->isEmergency());
        $this->assertNotNull($consent->signed_at);
        $this->assertSame('expired', $this->doctor->fresh()->credential_status);

        $closed = $emergency->close($encounter, 'stabil', $this->doctorUser);

        $this->assertSame(ClinicalEncounter::STATUS_COMPLETED, $closed->status);
        $this->assertSame('stabil', $closed->disposition);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_pdp_consent_grant_and_withdrawal_keep_history(): void
    {
        $service = app(ConsentService::class);

        $consent = $service->grantPdpConsent($this->owner, [
            'purpose' => 'layanan',
            'policy_version' => '2026.11',
            'policy_text' => 'Kebijakan privasi PetHeal versi 2026.11.',
            'ip_address' => '127.0.0.1',
        ], $this->owner);

        $this->assertSame($this->owner->id, $consent->user_id);
        $this->assertSame(64, strlen($consent->policy_hash));
        $this->assertNotNull($consent->granted_at);
        $this->assertNull($consent->withdrawn_at);
        $this->assertSame(1, Consent::active()->where('user_id', $this->owner->id)->count());

        $withdrawn = $service->withdrawPdpConsent($consent, 'Tidak ingin data dipakai untuk analitik.', $this->owner);

        $this->assertNotNull($withdrawn->withdrawn_at);
        $this->assertNotNull($withdrawn->revoked_at);
        $this->assertSame('Tidak ingin data dipakai untuk analitik.', $withdrawn->withdrawal_reason);
        $this->assertSame(0, Consent::active()->where('user_id', $this->owner->id)->count());

        try {
            $service->withdrawPdpConsent($withdrawn, 'Lagi.', $this->owner);
            $this->fail('Pencabutan PDP ganda seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CONSENT_PDP_ALREADY_WITHDRAWN', $e->errorCode);
        }
    }

    public function test_emergency_service_rejects_invalid_triage_and_cross_clinic_doctor(): void
    {
        $service = app(EmergencyEncounterService::class);

        try {
            $service->open($this->clinic, $this->pet, [
                'triage_level' => 'unknown',
            ], $this->doctorUser);
            $this->fail('Triage tidak valid seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EMERGENCY_TRIAGE_REQUIRED', $e->errorCode);
        }

        $other = Clinic::create([
            'name' => 'P22 Other ' . Str::random(4),
            'slug' => 'p22-other-' . Str::random(6),
            'address' => 'Jl. Lain',
            'is_active' => true,
        ]);

        $otherUser = User::create([
            'name' => 'Dokter Other P22',
            'email' => 'other' . Str::random(8) . '@p22.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $other->id,
        ]);

        $otherDoctor = Doctor::create([
            'user_id' => $otherUser->id,
            'name' => 'Dr Other',
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $other->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        try {
            $service->open($this->clinic, $this->pet, [
                'triage_level' => EmergencyEncounterService::TRIAGE_YELLOW,
                'attending_doctor_id' => $otherDoctor->id,
            ], $this->doctorUser);
            $this->fail('Dokter klinik lain seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EMERGENCY_DOCTOR_MISMATCH', $e->errorCode);
        }
    }
}
