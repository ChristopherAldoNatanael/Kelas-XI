<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Doctor;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabReferenceRange;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use App\Services\LabOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P2.1 — order/item/spesimen/hasil/verifikasi laboratorium.
 */
class P21LaboratoryWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $adminUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P21 ' . $suffix,
            'slug' => 'p21-' . $suffix,
            'address' => 'Jl. P21',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P21',
            'email' => 'doctor' . $suffix . '@p21.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin P21',
            'email' => 'admin' . $suffix . '@p21.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P21 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P21',
            'email' => 'owner' . $suffix . '@p21.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P21Pet',
            'species' => 'Cat',
            'breed' => 'Domestic Shorthair',
            'gender' => 'male',
            'weight' => 4,
            'date_of_birth' => now()->subYears(3)->toDateString(),
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
            'treatment' => 'Cek darah',
        ]);
    }

    public function test_order_items_and_specimens_have_separate_statuses(): void
    {
        $service = app(LabOrderService::class);

        $order = $service->create($this->record, [
            'priority' => LabOrder::PRIORITY_URGENT,
            'clinical_notes' => 'Curiga anemia.',
            'items' => [
                ['test_code' => 'CBC', 'test_name' => 'Complete Blood Count', 'specimen_type' => 'Darah'],
                ['test_code' => 'CHEM', 'test_name' => 'Kimia Darah', 'specimen_type' => 'Darah'],
            ],
        ], $this->doctorUser);

        $this->assertSame(LabOrder::STATUS_ORDERED, $order->status);
        $this->assertCount(2, $order->items);
        $this->assertSame($this->clinic->id, $order->clinic_id);
        $this->assertSame($this->record->encounter_id, $order->encounter_id);

        $specimen = $service->collectSpecimen($order, [
            'specimen_type' => 'Darah',
        ], $this->doctorUser);

        $this->assertSame(LabSpecimen::STATUS_COLLECTED, $specimen->status);
        $this->assertNotNull($specimen->accession_number);
        $this->assertSame(LabOrder::STATUS_PARTIALLY_COLLECTED, $order->fresh()->status);

        $received = $service->transitionSpecimen($specimen, LabSpecimen::STATUS_RECEIVED, $this->doctorUser);
        $this->assertSame(LabSpecimen::STATUS_RECEIVED, $received->status);

        try {
            $service->transitionSpecimen($received, LabSpecimen::STATUS_REJECTED, $this->doctorUser, '');
            $this->fail('Penolakan spesimen tanpa alasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LAB_SPECIMEN_REJECTION_REASON_REQUIRED', $e->errorCode);
        }
    }

    public function test_result_uses_matching_reference_and_doctor_verification(): void
    {
        LabReferenceRange::create([
            'clinic_id' => $this->clinic->id,
            'species' => 'Cat',
            'sex' => 'male',
            'age_min_days' => 365,
            'age_max_days' => 3650,
            'test_code' => 'CBC',
            'parameter' => 'Hemoglobin',
            'unit' => 'g/dL',
            'reference_low' => 8,
            'reference_high' => 15,
            'method' => 'Cyanmethemoglobin',
            'specimen_type' => 'Darah',
            'effective_from' => now()->subYear()->toDateString(),
            'is_active' => true,
        ]);

        $service = app(LabOrderService::class);
        $order = $this->order(['test_code' => 'CBC', 'test_name' => 'CBC', 'specimen_type' => 'Darah']);
        $item = $order->items->first();

        $result = $service->recordResult($order, $item, [
            'parameter' => 'Hemoglobin',
            'value' => '18.2',
            'unit' => 'g/dL',
            'method' => 'Cyanmethemoglobin',
        ], $this->doctorUser);

        $this->assertSame(LabResult::STATUS_PRELIMINARY, $result->status);
        $this->assertSame(LabResult::REFERENCE_MATCHED, $result->reference_status);
        $this->assertSame('high', $result->flag);
        $this->assertSame('8 - 15 g/dL', $result->reference_range);
        $this->assertSame('18.2', (string) $result->value);
        $this->assertSame($this->record->id, $result->medical_record_id);
        $this->assertSame(LabOrderItem::STATUS_RESULTED, $item->fresh()->status);

        try {
            $service->verifyResult($result, $this->adminUser);
            $this->fail('Admin seharusnya tidak dapat memverifikasi hasil lab.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LAB_VERIFIER_NOT_DOCTOR', $e->errorCode);
        }

        $verified = $service->verifyResult($result, $this->doctorUser);

        $this->assertSame(LabResult::STATUS_VERIFIED, $verified->status);
        $this->assertSame($this->doctorUser->id, $verified->verified_by);
        $this->assertNotNull($verified->verified_at);

        try {
            $service->verifyResult($verified, $this->doctorUser);
            $this->fail('Verifikasi ganda seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LAB_RESULT_ALREADY_VERIFIED', $e->errorCode);
        }
    }

    public function test_no_matching_reference_never_fabricates_normal_range(): void
    {
        $service = app(LabOrderService::class);
        $order = $this->order(['test_code' => 'UNKNOWN', 'test_name' => 'Pemeriksaan Langka']);

        $result = $service->recordResult($order, $order->items->first(), [
            'parameter' => 'Parameter X',
            'value' => '123',
        ], $this->doctorUser);

        $this->assertSame(LabResult::REFERENCE_UNAVAILABLE, $result->reference_status);
        $this->assertNull($result->reference_low);
        $this->assertNull($result->reference_high);
        $this->assertNull($result->reference_range);
        $this->assertNull($result->reference_range_id);
        $this->assertNull($result->flag);

        $femalePet = $this->pet->replicate();
        $femalePet->gender = 'female';
        $femalePet->save();

        LabReferenceRange::create([
            'clinic_id' => $this->clinic->id,
            'species' => 'Cat',
            'sex' => 'male',
            'test_code' => 'ONLYMALE',
            'parameter' => 'Test',
            'unit' => 'mg',
            'reference_low' => 1,
            'reference_high' => 2,
            'is_active' => true,
        ]);

        $resolved = app(\App\Services\LabReferenceService::class)->resolve(
            $femalePet,
            $this->clinic->id,
            'ONLYMALE',
            'Test'
        );

        $this->assertSame(LabResult::REFERENCE_UNAVAILABLE, $resolved['reference_status']);
        $this->assertNull($resolved['reference_range_id']);
    }

    public function test_legacy_result_cannot_be_verified_and_correction_creates_new_row(): void
    {
        $legacy = LabResult::create([
            'clinic_id' => $this->clinic->id,
            'medical_record_id' => $this->record->id,
            'test_name' => 'Kimia Darah Lama',
            'parameter' => 'Kreatinin',
            'value' => '2.5',
            'unit' => 'mg/dL',
            'status' => LabResult::STATUS_LEGACY,
            'reference_status' => LabResult::REFERENCE_UNAVAILABLE,
            'performed_at' => now()->subYear(),
        ]);

        $service = app(LabOrderService::class);

        try {
            $service->verifyResult($legacy, $this->doctorUser);
            $this->fail('Hasil legacy seharusnya tidak dapat diverifikasi langsung.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LAB_RESULT_LEGACY_REQUIRES_CORRECTION', $e->errorCode);
        }

        $correction = $service->correctResult(
            $legacy,
            ['value' => '1.8', 'notes' => 'Re-run dengan analyzer baru.'],
            'Kesalahan input nilai lama.',
            $this->doctorUser
        );

        $this->assertSame(LabResult::STATUS_CORRECTED, $correction->status);
        $this->assertSame($legacy->id, $correction->correction_of_id);
        $this->assertSame('1.8', (string) $correction->value);
        $this->assertSame(LabResult::STATUS_LEGACY, $legacy->fresh()->status);
        $this->assertSame('2.5', (string) $legacy->fresh()->value);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('lab_results')->where('id', $legacy->id)->update(['status' => 'verified']);
    }

    public function test_verified_result_is_immutable_in_database(): void
    {
        $service = app(LabOrderService::class);
        $order = $this->order(['test_code' => 'GLU', 'test_name' => 'Glukosa']);

        $result = $service->recordResult($order, $order->items->first(), [
            'parameter' => 'Glukosa',
            'value' => '90',
            'unit' => 'mg/dL',
        ], $this->doctorUser);

        $verified = $service->verifyResult($result, $this->doctorUser);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('lab_results')->where('id', $verified->id)->update(['value' => '999']);
    }

    public function test_additional_finding_requires_doctor_approval(): void
    {
        $service = app(LabOrderService::class);
        $order = $this->order(['test_code' => 'CBC', 'test_name' => 'CBC']);
        $item = $order->items->first();

        try {
            $service->recordResult($order, $item, [
                'parameter' => 'Temuan tambahan',
                'value' => 'ada',
                'is_additional' => true,
            ], $this->adminUser);
            $this->fail('Temuan tambahan oleh admin seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LAB_ADDITIONAL_APPROVER_REQUIRED', $e->errorCode);
        }
    }

    private function order(array $item): LabOrder
    {
        $service = app(LabOrderService::class);

        return $service->create($this->record, [
            'priority' => LabOrder::PRIORITY_ROUTINE,
            'items' => [$item],
        ], $this->doctorUser);
    }
}
