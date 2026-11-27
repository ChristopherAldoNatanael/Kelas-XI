<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordExport;
use App\Models\Pet;
use App\Models\User;
use App\Services\MedicalRecordExportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3.2 — ekspor resmi, tanda tangan, dan audit akses.
 */
class P32ExportSigningAndAuditTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $adminUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;
    private MedicalRecordExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P32 ' . $suffix,
            'slug' => 'p32-' . $suffix,
            'address' => 'Jl. P32',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P32',
            'email' => 'doctor' . $suffix . '@p32.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin P32',
            'email' => 'admin' . $suffix . '@p32.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P32 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P32',
            'email' => 'owner' . $suffix . '@p32.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P32Pet',
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
            'diagnosis' => 'Sehat',
            'treatment' => 'Observasi',
        ]);

        $this->service = app(MedicalRecordExportService::class);
    }

    public function test_official_export_requires_render_and_doctor_signature_before_token_can_be_used(): void
    {
        $created = $this->service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_PDF,
            'purpose' => MedicalRecordExport::PURPOSE_LEGAL,
            'recipient_name' => 'Pengadilan',
        ], $this->doctorUser);

        $export = $created['export'];

        try {
            $this->service->resolve($created['token'], $this->record->id, null, 'pdf', 'legal', '127.0.0.1', $this->doctorUser);
            $this->fail('Ekspor legal tanpa tanda tangan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_SIGNATURE_REQUIRED', $e->errorCode);
        }

        $this->assertSame(0, $export->fresh()->access_count);

        try {
            $this->service->sign($export, ['signature_path' => 'signatures/ttd.png'], $this->doctorUser);
            $this->fail('Tanda tangan sebelum dokumen dibuat seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_DOCUMENT_NOT_READY', $e->errorCode);
        }

        $renderedHash = hash('sha256', 'dokumen-resmi-p32');

        $rendered = $this->service->render($export, [
            'rendered_path' => 'exports/rekam-p32.pdf',
            'rendered_hash' => $renderedHash,
        ], $this->adminUser);

        $this->assertSame($renderedHash, $rendered->rendered_hash);
        $this->assertSame($renderedHash, $rendered->file_hash);
        $this->assertSame($this->record->content_hash, $rendered->content_hash);

        try {
            $this->service->sign($rendered, ['signature_path' => 'signatures/ttd.png'], $this->adminUser);
            $this->fail('Admin tidak boleh menandatangani ekspor resmi.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_SIGNER_NOT_DOCTOR', $e->errorCode);
        }

        try {
            $this->service->sign($rendered, [], $this->doctorUser);
            $this->fail('Tanda tangan tanpa path seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_SIGNATURE_PATH_REQUIRED', $e->errorCode);
        }

        $signed = $this->service->sign($rendered, [
            'signature_path' => 'signatures/ttd.png',
            'signature_hash' => hash('sha256', 'ttd-p32'),
        ], $this->doctorUser);

        $this->assertTrue($signed->isSigned());
        $this->assertSame($this->doctorUser->id, $signed->signed_by);
        $this->assertNotNull($signed->signed_at);
        $this->assertSame(64, strlen($signed->signature_hash));

        $resolved = $this->service->resolve($created['token'], $this->record->id, null, 'pdf', 'legal', '127.0.0.1', $this->doctorUser);

        $this->assertSame(1, $resolved->access_count);
        $this->assertNotNull($resolved->token_used_at);

        try {
            $this->service->sign($signed, [
                'signature_path' => 'signatures/ttd-2.png',
            ], $this->doctorUser);
            $this->fail('Tanda tangan ganda seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertTrue(in_array($e->errorCode, ['EXPORT_ALREADY_SIGNED', 'EXPORT_ALREADY_USED'], true));
        }
    }

    public function test_render_and_signature_hashes_are_validated(): void
    {
        $created = $this->service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_JSON,
            'purpose' => MedicalRecordExport::PURPOSE_INSURANCE,
        ], $this->doctorUser);

        try {
            $this->service->render($created['export'], [
                'rendered_path' => 'exports/x.json',
                'rendered_hash' => 'bukan-hash',
            ], $this->adminUser);
            $this->fail('Hash dokumen tidak valid seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_HASH_INVALID', $e->errorCode);
        }

        try {
            $this->service->render($created['export'], [], $this->adminUser);
            $this->fail('Path dokumen kosong seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_RENDERED_PATH_REQUIRED', $e->errorCode);
        }

        $rendered = $this->service->render($created['export'], [
            'rendered_path' => 'exports/x.json',
            'rendered_hash' => str_repeat('a', 64),
        ], $this->adminUser);

        try {
            $this->service->sign($rendered, [
                'signature_path' => 'signatures/x.png',
                'signature_hash' => 'pendek',
            ], $this->doctorUser);
            $this->fail('Hash tanda tangan tidak valid seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_HASH_INVALID', $e->errorCode);
        }
    }

    public function test_export_access_is_audited_without_storing_token_or_clinical_content(): void
    {
        $created = $this->service->create($this->record, [
            'format' => MedicalRecordExport::FORMAT_PDF,
            'purpose' => MedicalRecordExport::PURPOSE_OWNER_COPY,
        ], $this->doctorUser);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'medical_record.export.created',
            'result' => 'allowed',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->service->resolve($created['token'], $this->record->id, null, 'pdf', 'owner_copy', '10.0.0.1', $this->doctorUser);

        $allowed = AuditLog::where('action', 'medical_record.export.access')
            ->where('result', 'allowed')
            ->latest('id')
            ->first();

        $this->assertNotNull($allowed);
        $this->assertSame($this->clinic->id, $allowed->clinic_id);
        $this->assertNotEmpty($allowed->request_id);
        $this->assertSame('owner_copy', $allowed->metadata['purpose']);
        $this->assertArrayNotHasKey('token', $allowed->metadata);
        $this->assertArrayNotHasKey('diagnosis', $allowed->metadata);
        $this->assertStringNotContainsString($created['token'], json_encode($allowed->metadata));

        try {
            $this->service->resolve($created['token'], null, null, null, null, '10.0.0.2');
            $this->fail('Pemakaian token kedua seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('EXPORT_TOKEN_ALREADY_USED', $e->errorCode);
        }

        $denied = AuditLog::where('action', 'medical_record.export.access')
            ->where('result', 'denied')
            ->latest('id')
            ->first();

        $this->assertNotNull($denied);
        $this->assertSame('EXPORT_TOKEN_ALREADY_USED', $denied->reason);
        $this->assertSame('token', $denied->metadata['request_source']);
    }

    public function test_audit_logs_cannot_be_updated_or_deleted(): void
    {
        $log = AuditLog::create([
            'action' => 'p32.append_only_test',
            'result' => 'allowed',
            'clinic_id' => $this->clinic->id,
            'request_id' => Str::random(32),
        ]);

        try {
            DB::table('audit_logs')->where('id', $log->id)->update(['reason' => 'diubah']);
            $this->fail('Update audit_logs seharusnya ditolak database.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            DB::table('audit_logs')->where('id', $log->id)->delete();
            $this->fail('Delete audit_logs seharusnya ditolak database.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('tidak boleh dihapus', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'p32.append_only_test']);
    }
}
