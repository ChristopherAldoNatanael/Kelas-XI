<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\CredentialException;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\MedicalRecordCredentialSnapshot;
use App\Models\MedicalRecordVersion;
use App\Models\Paramedic;
use App\Models\Pet;
use App\Models\ProfessionalCredential;
use App\Models\User;
use App\Services\MedicalRecordAmendmentService;
use App\Services\MedicalRecordLifecycleService;
use App\Services\ParamedicAuthorizationService;
use App\Services\ProfessionalCredentialService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1.1 — gate SIP/STR, riwayat kredensial, snapshot immutable, paramedik.
 */
class P11CredentialGateTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $admin;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecordLifecycleService $lifecycle;
    private ProfessionalCredentialService $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P11 ' . $suffix,
            'slug' => 'p11-' . $suffix,
            'address' => 'Jl. P11',
            'license_number' => 'KLINIK-P11-' . strtoupper($suffix),
            'license_expiry' => now()->addYears(3)->toDateString(),
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P11',
            'email' => 'doctor' . $suffix . '@p11.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->admin = User::create([
            'name' => 'Admin P11',
            'email' => 'admin' . $suffix . '@p11.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P11 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P11',
            'email' => 'owner' . $suffix . '@p11.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P11Pet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $this->lifecycle = app(MedicalRecordLifecycleService::class);
        $this->credentials = app(ProfessionalCredentialService::class);

        config()->set('medical_records.lifecycle_enforcement_enabled', false);
        config()->set('medical_records.amendments_enabled', false);
        config()->set('medical_records.credential_gate_enabled', true);
    }

    public function test_finalization_without_credentials_is_rejected(): void
    {
        $record = $this->record();

        try {
            $this->lifecycle->finalize($record, $this->doctorUser);
            $this->fail('Finalisasi tanpa STR/SIP seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CREDENTIAL_MISSING', $e->errorCode);
            $this->assertSame(422, $e->httpStatus);
            $this->assertNotEmpty($e->context['errors']);
        }

        $this->assertSame(MedicalRecord::STATUS_DRAFT, $record->refresh()->status);
        $this->assertSame(0, MedicalRecordVersion::where('medical_record_id', $record->id)->count());
    }

    public function test_valid_credentials_allow_finalization_and_snapshot_each_version(): void
    {
        $this->issueDoctorCredentials();

        $record = $this->record();
        $version = $this->lifecycle->finalize($record, $this->doctorUser);

        $snapshots = MedicalRecordCredentialSnapshot::where('version_id', $version->id)->get();

        $this->assertCount(3, $snapshots);
        $this->assertSame(
            ['CLINIC_LICENSE', 'SIP', 'STR'],
            $snapshots->pluck('credential_type')->sort()->values()->all()
        );
        $this->assertTrue($snapshots->every(
            fn ($snapshot) => $snapshot->credential_status === ProfessionalCredentialService::SNAPSHOT_VALID
        ));
        $this->assertSame($record->clinic_id, $snapshots->first()->clinic_id);
    }

    public function test_expired_sip_rejects_normal_finalization(): void
    {
        $this->issueDoctorCredentials();
        $this->issueCredential(ProfessionalCredential::TYPE_SIP, 'SIP-OLD', now()->subDay());

        $record = $this->record();

        try {
            $this->lifecycle->finalize($record, $this->doctorUser);
            $this->fail('SIP kedaluwarsa seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CREDENTIAL_EXPIRED', $e->errorCode);
            $this->assertSame(422, $e->httpStatus);
        }

        $this->assertSame(MedicalRecord::STATUS_DRAFT, $record->refresh()->status);
    }

    public function test_new_credential_supersedes_old_without_deleting_history(): void
    {
        $first = $this->issueCredential(ProfessionalCredential::TYPE_SIP, 'SIP-A');
        $second = $this->issueCredential(ProfessionalCredential::TYPE_SIP, 'SIP-B');

        $first->refresh();

        $this->assertFalse($first->is_current);
        $this->assertNull($first->active_key);
        $this->assertNotNull($first->superseded_at);
        $this->assertTrue($second->is_current);
        $this->assertNotNull($second->active_key);
        $this->assertSame(2, ProfessionalCredential::where('doctor_id', $this->doctor->id)
            ->where('credential_type', ProfessionalCredential::TYPE_SIP)->count());
    }

    public function test_old_snapshot_does_not_change_when_master_credential_changes(): void
    {
        $this->issueDoctorCredentials();

        $firstRecord = $this->record();
        $firstVersion = $this->lifecycle->finalize($firstRecord, $this->doctorUser);
        $oldSip = MedicalRecordCredentialSnapshot::where('version_id', $firstVersion->id)
            ->where('credential_type', 'SIP')->firstOrFail();

        $this->issueCredential(ProfessionalCredential::TYPE_SIP, 'SIP-NEW');

        $secondRecord = $this->record();
        $secondVersion = $this->lifecycle->finalize($secondRecord, $this->doctorUser);
        $newSip = MedicalRecordCredentialSnapshot::where('version_id', $secondVersion->id)
            ->where('credential_type', 'SIP')->firstOrFail();

        $this->assertSame('SIP-VALID', $oldSip->number);
        $this->assertSame('SIP-NEW', $newSip->number);

        try {
            $oldSip->update(['number' => 'DIUBAH']);
            $this->fail('Snapshot seharusnya append-only.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_emergency_exception_does_not_make_expired_credential_valid(): void
    {
        $this->issueDoctorCredentials();
        $expired = $this->issueCredential(ProfessionalCredential::TYPE_SIP, 'SIP-EXPIRED', now()->subDay());
        $record = $this->record();

        CredentialException::create([
            'clinic_id' => $this->clinic->id,
            'medical_record_id' => $record->id,
            'holder_type' => 'doctor',
            'holder_id' => $this->doctor->id,
            'credential_type' => 'SIP',
            'credential_status' => 'expired',
            'emergency_context' => 'Pasien datang dalam kondisi darurat.',
            'legal_basis' => 'SOP klinik darurat.',
            'status' => CredentialException::STATUS_OPEN,
        ]);

        $this->assertSame(ProfessionalCredential::STATUS_EXPIRED, $expired->refresh()->status);

        try {
            $this->lifecycle->finalize($record, $this->doctorUser);
            $this->fail('Exception tidak boleh mengubah status kredensial.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CREDENTIAL_EXPIRED', $e->errorCode);
        }

        $this->assertSame(1, CredentialException::where('medical_record_id', $record->id)->count());
    }

    public function test_paramedic_action_requires_sipp_and_authorization_mode(): void
    {
        $paramedic = Paramedic::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Paramedik P11',
            'supervising_doctor_id' => $this->doctor->id,
            'is_active' => true,
        ]);

        $auth = app(ParamedicAuthorizationService::class);

        $this->issueCredential(ProfessionalCredential::TYPE_SIPP, 'SIPP-P11', null, $paramedic);

        $auth->issueAuthorization($paramedic, [
            'action_code' => 'vital_signs',
            'supervision_mode' => 'none',
        ], $this->doctorUser);

        $this->assertSame('vital_signs', $auth->authorizeAction($paramedic, 'vital_signs')->action_code);

        try {
            $auth->authorizeAction($paramedic, 'diagnosis_definitive');
            $this->fail('Tindakan di luar otorisasi seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('PARAMEDIC_NOT_AUTHORIZED', $e->errorCode);
            $this->assertSame(403, $e->httpStatus);
        }

        $auth->issueAuthorization($paramedic, [
            'action_code' => 'wound_suturing',
            'supervision_mode' => 'real_time',
        ], $this->doctorUser);

        try {
            $auth->authorizeAction($paramedic, 'wound_suturing');
            $this->fail('Tindakan real_time tanpa konfirmasi dokter harus ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('PARAMEDIC_NOT_AUTHORIZED', $e->errorCode);
        }

        $this->assertSame(
            'wound_suturing',
            $auth->authorizeAction($paramedic, 'wound_suturing', null, true)->action_code
        );

        try {
            $auth->issueAuthorization($paramedic, [
                'action_code' => 'prescribing',
                'supervision_mode' => 'real_time',
            ], $this->admin);
            $this->fail('Admin tidak boleh memberi kewenangan klinis.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CLINICAL_APPROVER_REQUIRED', $e->errorCode);
        }
    }

    public function test_amendment_records_reviewer_credential_snapshot(): void
    {
        config()->set('medical_records.amendments_enabled', true);
        $this->issueDoctorCredentials();

        $record = $this->record();
        $this->lifecycle->finalize($record, $this->doctorUser);

        $amendments = app(MedicalRecordAmendmentService::class);
        $amendment = $amendments->request(
            $record->refresh(),
            $this->admin,
            MedicalRecordAmendment::TYPE_CLINICAL,
            'Koreksi diagnosis.',
            ['diagnosis' => 'Koreksi']
        );

        $approved = $amendments->approve($amendment, $this->doctorUser, 'Disetujui.');

        $this->assertSame('valid', $approved->reviewer_credential_snapshot['status']);
        $this->assertCount(2, $approved->reviewer_credential_snapshot['credentials']);
    }

    private function record(): MedicalRecord
    {
        return MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'status' => MedicalRecord::STATUS_DRAFT,
            'diagnosis' => 'Enteritis',
            'treatment' => 'Cairan dan observasi',
        ]);
    }

    private function issueDoctorCredentials(): void
    {
        $this->issueCredential(ProfessionalCredential::TYPE_STR, 'STR-VALID');
        $this->issueCredential(ProfessionalCredential::TYPE_SIP, 'SIP-VALID');
    }

    private function issueCredential(
        string $type,
        string $number,
        mixed $expiry = null,
        ?Paramedic $paramedic = null
    ): ProfessionalCredential {
        $expiry = $expiry ?: now()->addYears(2);

        return $this->credentials->issue([
            'clinic_id' => $this->clinic->id,
            'doctor_id' => $paramedic ? null : $this->doctor->id,
            'paramedic_id' => $paramedic?->id,
            'credential_type' => $type,
            'number' => $number,
            'expires_at' => $expiry->toDateString(),
            'status' => ProfessionalCredential::STATUS_ACTIVE,
        ], $this->doctorUser);
    }
}
