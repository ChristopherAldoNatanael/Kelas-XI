<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\NotifiableDiseaseReport;
use App\Models\Pet;
use App\Models\User;
use App\Services\NotifiableDiseaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3.1 — laporan penyakit menular, tenggat 24 jam, bukti, eskalasi.
 */
class P31NotifiableDiseaseReportTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $adminUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;
    private NotifiableDiseaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P31 ' . $suffix,
            'slug' => 'p31-' . $suffix,
            'address' => 'Jl. P31',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P31',
            'email' => 'doctor' . $suffix . '@p31.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin P31',
            'email' => 'admin' . $suffix . '@p31.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P31 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P31',
            'email' => 'owner' . $suffix . '@p31.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P31Pet',
            'species' => 'Swine',
            'gender' => 'male',
            'weight' => 50,
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
            'diagnosis' => 'Suspek penyakit menular',
            'treatment' => 'Observasi',
        ]);

        $this->service = app(NotifiableDiseaseService::class);
    }

    public function test_report_is_created_as_draft_with_exact_24_hour_deadline(): void
    {
        $indicated = now()->subHour()->startOfSecond();

        $report = $this->service->create($this->record, [
            'disease_code' => 'asf',
            'disease_name' => 'African Swine Fever',
            'classification' => NotifiableDiseaseReport::CLASSIFICATION_SUSPECTED,
            'indicated_at' => $indicated->toDateTimeString(),
            'notes' => 'Demam tinggi dan kematian mendadak.',
        ], $this->doctorUser);

        $this->assertSame($this->clinic->id, $report->clinic_id);
        $this->assertSame($this->record->id, $report->medical_record_id);
        $this->assertSame($this->record->encounter_id, $report->encounter_id);
        $this->assertSame('ASF', $report->disease_code);
        $this->assertSame(NotifiableDiseaseReport::STATUS_DRAFT, $report->status);
        $this->assertEquals(86400, $report->indicated_at->diffInSeconds($report->report_deadline_at));
        $this->assertNull($report->reported_at);
        $this->assertNull($report->escalated_at);
        $this->assertFalse($report->isLate());
    }

    public function test_report_rejects_invalid_classification_future_indication_and_missing_encounter(): void
    {
        try {
            $this->service->create($this->record, [
                'disease_code' => 'ASF',
                'disease_name' => 'African Swine Fever',
                'classification' => 'maybe',
            ], $this->doctorUser);
            $this->fail('Klasifikasi tidak valid seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_CLASSIFICATION_INVALID', $e->errorCode);
        }

        try {
            $this->service->create($this->record, [
                'disease_code' => 'ASF',
                'disease_name' => 'African Swine Fever',
                'classification' => NotifiableDiseaseReport::CLASSIFICATION_CONFIRMED,
                'indicated_at' => now()->addHour()->toDateTimeString(),
            ], $this->doctorUser);
            $this->fail('Waktu indikasi di masa depan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_INDICATED_IN_FUTURE', $e->errorCode);
        }

        $recordWithoutEncounter = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Tanpa encounter',
            'treatment' => 'Observasi',
        ]);

        try {
            $this->service->create($recordWithoutEncounter, [
                'disease_code' => 'ASF',
                'disease_name' => 'African Swine Fever',
                'classification' => NotifiableDiseaseReport::CLASSIFICATION_PROBABLE,
            ], $this->doctorUser);
            $this->fail('Rekam tanpa encounter seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_ENCOUNTER_REQUIRED', $e->errorCode);
        }
    }

    public function test_report_lifecycle_requires_evidence_and_doctor_verification_with_late_flag(): void
    {
        $report = $this->overdueReport();
        $ready = $this->service->markReady($report, $this->doctorUser);

        $this->assertSame(NotifiableDiseaseReport::STATUS_READY, $ready->status);

        try {
            $this->service->markReported($ready, [], $this->doctorUser);
            $this->fail('Laporan tanpa referensi/bukti seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_EVIDENCE_REQUIRED', $e->errorCode);
        }

        $reported = $this->service->markReported($ready, [
            'isikhnas_reference' => 'iSIKHNAS-2026-0001',
            'evidence_path' => 'reports/isikhnas-0001.pdf',
            'notes' => 'Dilaporkan melalui kanal resmi.',
        ], $this->doctorUser);

        $this->assertSame(NotifiableDiseaseReport::STATUS_REPORTED, $reported->status);
        $this->assertNotNull($reported->reported_at);
        $this->assertSame($this->doctorUser->id, $reported->reported_by);
        $this->assertSame('iSIKHNAS-2026-0001', $reported->isikhnas_reference);
        $this->assertTrue($reported->isLate());
        $this->assertFalse($reported->isOverdue());

        try {
            $this->service->verify($reported, $this->adminUser);
            $this->fail('Admin tidak boleh memverifikasi laporan penyakit menular.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_VERIFIER_NOT_DOCTOR', $e->errorCode);
        }

        $verified = $this->service->verify($reported, $this->doctorUser);

        $this->assertSame(NotifiableDiseaseReport::STATUS_VERIFIED, $verified->status);
        $this->assertSame($this->doctorUser->id, $verified->verified_by);
        $this->assertNotNull($verified->verified_at);

        try {
            $this->service->cancel($verified, 'Salah input.', $this->doctorUser);
            $this->fail('Laporan terverifikasi seharusnya tidak dapat dibatalkan.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_VERIFIED_IMMUTABLE', $e->errorCode);
        }
    }

    public function test_cancellation_requires_reason_and_blocks_further_transitions(): void
    {
        $report = $this->service->create($this->record, [
            'disease_code' => 'RABIES',
            'disease_name' => 'Rabies',
            'classification' => NotifiableDiseaseReport::CLASSIFICATION_PROBABLE,
        ], $this->doctorUser);

        try {
            $this->service->cancel($report, '   ', $this->doctorUser);
            $this->fail('Pembatalan tanpa alasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_CANCEL_REASON_REQUIRED', $e->errorCode);
        }

        $cancelled = $this->service->cancel($report, 'Hasil lab negatif.', $this->doctorUser);

        $this->assertSame(NotifiableDiseaseReport::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Hasil lab negatif.', $cancelled->cancel_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame($this->doctorUser->id, $cancelled->cancelled_by);

        try {
            $this->service->markReady($cancelled, $this->doctorUser);
            $this->fail('Laporan cancelled tidak boleh menjadi ready.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('NOTIFIABLE_CANCELLED', $e->errorCode);
        }
    }

    public function test_overdue_command_escalates_only_unreported_overdue_reports_once(): void
    {
        $overdue = $this->overdueReport();
        $notOverdue = $this->service->create($this->record, [
            'disease_code' => 'FMD',
            'disease_name' => 'Foot and Mouth Disease',
            'classification' => NotifiableDiseaseReport::CLASSIFICATION_SUSPECTED,
        ], $this->doctorUser);

        $this->artisan('reports:escalate-overdue')
            ->expectsOutputToContain('Laporan lewat tenggat belum dieskalasi: 1')
            ->assertSuccessful();

        $this->assertNotNull($overdue->fresh()->escalated_at);
        $this->assertNull($notOverdue->fresh()->escalated_at);

        $this->artisan('reports:escalate-overdue')
            ->expectsOutputToContain('Laporan lewat tenggat belum dieskalasi: 0')
            ->assertSuccessful();

        $this->assertNotNull($overdue->fresh()->escalated_at);
    }

    private function overdueReport(): NotifiableDiseaseReport
    {
        return $this->service->create($this->record, [
            'disease_code' => 'ASF',
            'disease_name' => 'African Swine Fever',
            'classification' => NotifiableDiseaseReport::CLASSIFICATION_CONFIRMED,
            'indicated_at' => now()->subHours(30)->toDateTimeString(),
        ], $this->doctorUser);
    }
}
