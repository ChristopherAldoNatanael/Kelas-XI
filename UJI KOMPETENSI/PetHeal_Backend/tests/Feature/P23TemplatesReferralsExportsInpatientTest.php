<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalTemplate;
use App\Models\Doctor;
use App\Models\InpatientLocationLog;
use App\Models\InpatientStay;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordExport;
use App\Models\Medicine;
use App\Models\Pet;
use App\Models\Referral;
use App\Models\User;
use App\Services\ClinicalTemplateService;
use App\Services\InpatientService;
use App\Services\MedicalRecordExportService;
use App\Services\MedicationAdministrationService;
use App\Services\MedicineStockService;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P2.3 — template klinis, rujukan, ekspor sekali pakai, rawat inap.
 */
class P23TemplatesReferralsExportsInpatientTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $adminUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;
    private ClinicalEncounter $encounter;
    private Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P23 ' . $suffix,
            'slug' => 'p23-' . $suffix,
            'address' => 'Jl. P23',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P23',
            'email' => 'doctor' . $suffix . '@p23.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin P23',
            'email' => 'admin' . $suffix . '@p23.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P23 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P23',
            'email' => 'owner' . $suffix . '@p23.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P23Pet',
            'species' => 'Dog',
            'gender' => 'male',
            'weight' => 12,
        ]);

        $this->encounter = ClinicalEncounter::create([
            'clinic_id' => $this->clinic->id,
            'pet_id' => $this->pet->id,
            'type' => ClinicalEncounter::TYPE_INPATIENT,
            'status' => ClinicalEncounter::STATUS_IN_PROGRESS,
            'attending_doctor_id' => $this->doctor->id,
            'opened_at' => now(),
            'created_by' => $this->doctorUser->id,
        ]);

        $this->record = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'encounter_id' => $this->encounter->id,
            'diagnosis' => 'Gastroenteritis',
            'treatment' => 'Rawat inap',
        ]);

        $this->medicine = Medicine::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Metronidazole P23 ' . $suffix,
            'unit' => 'tablet',
            'price' => 3000,
            'registration_status' => Medicine::REGISTRATION_REGISTERED,
            'registration_number' => 'REG-P23-' . strtoupper($suffix),
            'registration_expires_at' => now()->addYear()->toDateString(),
        ]);

        app(MedicineStockService::class)->setOpeningStock($this->medicine, 10);
    }

    public function test_clinical_templates_are_versioned_and_schema_validated(): void
    {
        $service = app(ClinicalTemplateService::class);
        $schema = ['fields' => [
            ['key' => 'temperature', 'label' => 'Suhu', 'type' => 'number'],
            ['key' => 'notes', 'label' => 'Catatan', 'type' => 'text'],
        ]];

        $v1 = $service->publish($this->clinic, [
            'template_key' => 'soap_umum',
            'name' => 'SOAP Umum v1',
            'species' => 'Dog',
            'schema' => $schema,
        ], $this->doctorUser);

        $v2 = $service->publish($this->clinic, [
            'template_key' => 'soap_umum',
            'name' => 'SOAP Umum v2',
            'schema' => json_encode($schema),
        ], $this->doctorUser);

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);
        $this->assertFalse($v1->fresh()->is_current);
        $this->assertNull($v1->fresh()->active_key);
        $this->assertTrue($v2->fresh()->is_current);
        $this->assertEquals($schema, $v2->fresh()->schema);
        $this->assertSame($v2->id, $service->current($this->clinic, 'soap_umum', $this->doctorUser)->id);
        $this->assertSame(1, ClinicalTemplate::where('clinic_id', $this->clinic->id)->where('template_key', 'soap_umum')->where('is_current', true)->count());

        try {
            $service->publish($this->clinic, [
                'template_key' => 'soap_umum',
                'name' => 'Rusak',
                'schema' => ['fields' => []],
            ], $this->doctorUser);
            $this->fail('Schema tanpa field seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('TEMPLATE_SCHEMA_INVALID', $e->errorCode);
        }
    }

    public function test_referral_has_summary_hash_doctor_verification_and_void_not_delete(): void
    {
        $service = app(ReferralService::class);

        $referral = $service->create($this->record, [
            'direction' => Referral::DIRECTION_OUTGOING,
            'referred_to' => 'RS Hewan Bandung',
            'external_facility' => 'RS Hewan Bandung',
            'reason' => 'Butuh USG lanjutan',
            'summary' => 'Muntah 3 hari, dehidrasi, curiga obstruksi.',
        ], $this->doctorUser);

        $this->assertSame($this->clinic->id, $referral->clinic_id);
        $this->assertSame(Referral::STATUS_SENT, $referral->status);
        $this->assertStringStartsWith('RJ-' . $this->clinic->id . '-', $referral->referral_number);
        $this->assertSame(64, strlen($referral->document_hash));
        $this->assertNull($referral->verified_at);

        try {
            $service->verify($referral, $this->adminUser);
            $this->fail('Admin tidak boleh memverifikasi rujukan.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('REFERRAL_VERIFIER_NOT_DOCTOR', $e->errorCode);
        }

        $verified = $service->verify($referral, $this->doctorUser);
        $this->assertSame($this->doctorUser->id, $verified->verified_by);
        $this->assertNotNull($verified->verified_at);

        $accepted = $service->markAccepted($verified, $this->doctorUser);
        $this->assertSame(Referral::STATUS_ACCEPTED, $accepted->status);

        $returned = $service->markReturned($accepted, 'Tidak ada obstruksi, terapi medis.', $this->doctorUser);
        $this->assertSame(Referral::STATUS_RETURNED, $returned->status);
        $this->assertNotNull($returned->returned_at);
        $this->assertSame('Tidak ada obstruksi, terapi medis.', $returned->result_notes);

        try {
            $service->void($returned, '', $this->doctorUser);
            $this->fail('Void tanpa alasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('REFERRAL_VOID_REASON_REQUIRED', $e->errorCode);
        }

        $voided = $service->void($returned, 'Salah tujuan rujukan.', $this->doctorUser);
        $this->assertSame(Referral::STATUS_VOID, $voided->status);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame('Salah tujuan rujukan.', $voided->void_reason);
        $this->assertDatabaseHas('referrals', ['id' => $voided->id, 'status' => 'void']);

        try {
            $service->markAccepted($voided, $this->doctorUser);
            $this->fail('Rujukan void tidak boleh berubah status.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('REFERRAL_VOIDED', $e->errorCode);
        }

        $incoming = $service->create($this->record, [
            'direction' => Referral::DIRECTION_INCOMING,
            'referred_to' => 'PetHeal',
            'reason' => 'Rujukan masuk',
        ], $this->doctorUser);

        $this->assertSame(Referral::DIRECTION_INCOMING, $incoming->direction);
        $this->assertNull($incoming->referral_number);
        $this->assertSame(64, strlen($incoming->document_hash));
    }

    public function test_export_token_is_single_use_expiring_revocable_and_bound(): void
    {
        $service = app(MedicalRecordExportService::class);

        $created = $service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_PDF,
            'purpose' => MedicalRecordExport::PURPOSE_OWNER_COPY,
            'recipient_name' => 'Pemilik P23',
        ], $this->doctorUser);

        $export = $created['export'];
        $token = $created['token'];

        $this->assertGreaterThanOrEqual(43, strlen($token));
        $this->assertSame(64, strlen($export->token_hash));
        $this->assertNotSame($token, $export->token_hash);
        $this->assertFalse($export->isUsed());
        $this->assertFalse($export->isExpired());

        $resolved = $service->resolve($token, $this->record->id, $export->version_id, 'pdf', 'owner_copy', '127.0.0.1');

        $this->assertSame($export->id, $resolved->id);
        $this->assertNotNull($resolved->token_used_at);
        $this->assertNotNull($resolved->downloaded_at);
        $this->assertSame(1, $resolved->access_count);
        $this->assertSame('127.0.0.1', $resolved->last_accessed_ip);

        try {
            $service->resolve($token, $this->record->id, null, null, null, null);
            $this->fail('Token yang sudah dipakai seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_TOKEN_ALREADY_USED', $e->errorCode);
        }

        $expired = $service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_JSON,
            'purpose' => MedicalRecordExport::PURPOSE_REFERRAL,
        ], $this->doctorUser);

        $expired['export']->forceFill(['expires_at' => now()->subMinute()])->save();

        try {
            $service->resolve($expired['token'], null, null, null, null, null);
            $this->fail('Token kedaluwarsa seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_TOKEN_EXPIRED', $e->errorCode);
        }

        $revoked = $service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_CSV,
            'purpose' => MedicalRecordExport::PURPOSE_INSURANCE,
        ], $this->doctorUser);

        $service->revoke($revoked['export'], 'Salah penerima.', $this->doctorUser);

        try {
            $service->resolve($revoked['token'], null, null, null, null, null);
            $this->fail('Token dicabut seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_TOKEN_REVOKED', $e->errorCode);
        }

        $bound = $service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_PDF,
            'purpose' => MedicalRecordExport::PURPOSE_LEGAL,
        ], $this->doctorUser);

        try {
            $service->resolve($bound['token'], $this->record->id + 1, null, null, null, null);
            $this->fail('Token terikat rekam medis lain seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_TOKEN_MISMATCH', $e->errorCode);
        }

        try {
            $service->create($this->record, ['ttl_minutes' => 2000], $this->doctorUser);
            $this->fail('TTL melebihi 24 jam seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_TTL_INVALID', $e->errorCode);
        }
    }

    public function test_inpatient_location_history_discharge_and_medication_idempotency(): void
    {
        $inpatient = app(InpatientService::class);

        $stay = $inpatient->admit($this->record, [
            'ward' => 'Ruang A',
            'kennel_code' => 'A-01',
        ], $this->doctorUser);

        $this->assertSame(InpatientStay::STATUS_ADMITTED, $stay->status);
        $this->assertSame($this->encounter->id, $stay->encounter_id);
        $this->assertSame(1, $stay->locationLogs()->count());

        try {
            $inpatient->admit($this->record, [], $this->doctorUser);
            $this->fail('Encounter yang sama tidak boleh punya dua rawat inap.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('INPATIENT_ALREADY_ADMITTED', $e->errorCode);
        }

        $moved = $inpatient->transfer($stay, [
            'ward' => 'Ruang B',
            'kennel_code' => 'B-02',
            'reason' => 'Observasi lebih intensif',
        ], $this->doctorUser);

        $this->assertSame('Ruang B', $moved->ward);
        $this->assertSame('B-02', $moved->kennel_code);
        $this->assertSame(InpatientStay::STATUS_IN_TREATMENT, $moved->status);
        $this->assertSame(2, InpatientLocationLog::where('inpatient_stay_id', $stay->id)->count());

        $meds = app(MedicationAdministrationService::class);
        $batch = $this->medicine->batches()->first();

        $payload = [
            'order_source' => 'emergency',
            'medicine_id' => $this->medicine->id,
            'batch_id' => $batch?->id,
            'dose_value' => 250,
            'dose_unit' => 'mg',
            'route' => 'IV',
            'idempotency_key' => 'P23-MED-1',
        ];

        $first = $meds->administer($moved, $payload, $this->doctorUser);
        $second = $meds->administer($moved, $payload, $this->doctorUser);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $moved->medicationAdministrations()->count());
        $this->assertSame(250.0, (float) $first->dose_value);

        try {
            $meds->administer($moved, [
                'order_source' => 'prescription',
                'medicine_id' => $this->medicine->id,
                'idempotency_key' => 'P23-MED-2',
            ], $this->doctorUser);
            $this->fail('Sumber resep tanpa item resep seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('MEDICATION_PRESCRIPTION_ITEM_REQUIRED', $e->errorCode);
        }

        try {
            $inpatient->discharge($moved, [], $this->doctorUser);
            $this->fail('Discharge tanpa ringkasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('INPATIENT_SUMMARY_REQUIRED', $e->errorCode);
        }

        $discharged = $inpatient->discharge($moved, [
            'discharge_summary' => 'Membaik, makan normal.',
            'discharge_instructions' => 'Kontrol 3 hari.',
        ], $this->doctorUser);

        $this->assertSame(InpatientStay::STATUS_DISCHARGED, $discharged->status);
        $this->assertNotNull($discharged->discharged_at);

        try {
            $meds->administer($discharged, [
                'order_source' => 'emergency',
                'medicine_id' => $this->medicine->id,
                'idempotency_key' => 'P23-MED-3',
            ], $this->doctorUser);
            $this->fail('Obat setelah pulang seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('INPATIENT_NOT_ACTIVE', $e->errorCode);
        }
    }
}
