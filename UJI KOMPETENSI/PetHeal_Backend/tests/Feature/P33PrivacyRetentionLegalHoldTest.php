<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\LegalHoldService;
use App\Services\PrivacyRequestService;
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3.3 — privacy request, retensi, legal hold, dan dual approval.
 */
class P33PrivacyRetentionLegalHoldTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $owner;
    private User $adminOne;
    private User $adminTwo;
    private User $doctorUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P33 ' . $suffix,
            'slug' => 'p33-' . $suffix,
            'address' => 'Jl. P33',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner P33',
            'email' => 'owner' . $suffix . '@p33.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->adminOne = User::create([
            'name' => 'Admin Satu P33',
            'email' => 'admin1' . $suffix . '@p33.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminTwo = User::create([
            'name' => 'Admin Dua P33',
            'email' => 'admin2' . $suffix . '@p33.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P33',
            'email' => 'doctor' . $suffix . '@p33.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P33 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'P33Pet',
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
            'extra_payment_status' => 'not_required',
        ]);
    }

    public function test_privacy_request_lifecycle_is_audited_and_owner_cannot_handle_it(): void
    {
        $service = app(PrivacyRequestService::class);

        $request = $service->create($this->owner, [
            'request_type' => PrivacyRequest::TYPE_ACCESS,
            'description' => 'Minta salinan rekam medis hewan saya.',
            'clinic_id' => $this->clinic->id,
        ], $this->owner);

        $this->assertSame(PrivacyRequest::STATUS_RECEIVED, $request->status);
        $this->assertSame($this->owner->id, $request->user_id);
        $this->assertSame($this->clinic->id, $request->clinic_id);
        $this->assertEquals(14, $request->requested_at->diffInDays($request->due_at));
        $this->assertFalse($request->isOverdue());

        try {
            $service->verify($request, $this->owner);
            $this->fail('Pemilik tidak boleh memverifikasi permintaannya sendiri.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('PRIVACY_HANDLER_NOT_AUTHORIZED', $e->errorCode);
        }

        $verified = $service->verify($request, $this->adminOne);
        $this->assertSame(PrivacyRequest::STATUS_VERIFIED, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertSame($this->adminOne->id, $verified->handled_by);

        $started = $service->start($verified, $this->adminOne);
        $this->assertSame(PrivacyRequest::STATUS_IN_PROGRESS, $started->status);

        $completed = $service->complete($started, [
            'response_path' => 'privacy/response-p33.pdf',
        ], $this->adminOne);

        $this->assertSame(PrivacyRequest::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame('privacy/response-p33.pdf', $completed->response_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'privacy_request.completed',
            'model_id' => $completed->id,
            'result' => 'allowed',
        ]);
    }

    public function test_privacy_rejection_requires_reason_and_erasure_is_blocked_by_retention(): void
    {
        $service = app(PrivacyRequestService::class);

        $this->record->forceFill([
            'status' => MedicalRecord::STATUS_FINAL,
            'retention_until' => now()->addYears(3)->toDateString(),
            'finalized_at' => now()->subYear(),
        ])->save();

        $this->assertNotNull($service->erasureBlockReason($this->owner));

        $request = $service->create($this->owner, [
            'request_type' => PrivacyRequest::TYPE_ERASURE,
            'description' => 'Hapus semua data saya.',
            'clinic_id' => $this->clinic->id,
        ], $this->owner);

        try {
            $service->reject($request, ' ', $this->adminOne);
            $this->fail('Penolakan tanpa alasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('PRIVACY_REJECTION_REASON_REQUIRED', $e->errorCode);
        }

        $rejected = $service->reject(
            $request,
            'Rekam medis masih wajib disimpan sesuai retensi.',
            $this->adminOne
        );

        $this->assertSame(PrivacyRequest::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Rekam medis masih wajib disimpan sesuai retensi.', $rejected->rejection_reason);
        $this->assertNotNull($rejected->completed_at);

        $oldRecord = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Lama',
            'treatment' => 'Selesai',
            'status' => MedicalRecord::STATUS_FINAL,
            'extra_payment_status' => 'not_required',
        ]);
        $oldRecord->forceFill(['created_at' => now()->subYears(6)])->save();

        $this->assertNull($service->erasureBlockReason(User::create([
            'name' => 'Tanpa Rekam',
            'email' => 'empty' . Str::random(8) . '@p33.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ])));
    }

    public function test_retention_uses_financial_years_and_archive_respects_legal_hold(): void
    {
        $retention = app(RetentionService::class);

        $this->record->forceFill([
            'status' => MedicalRecord::STATUS_FINAL,
            'extra_payment_status' => 'unpaid',
            'created_at' => now()->subYears(6),
            'retention_until' => null,
        ])->save();

        $until = $retention->retentionUntil($this->record);
        $this->assertSame(now()->subYears(6)->addYears(10)->toDateString(), $until->toDateString());
        $this->assertFalse($retention->canArchive($this->record));

        $this->record->forceFill(['retention_until' => now()->subDay()->toDateString()])->save();
        $this->assertTrue($retention->canArchive($this->record->fresh()));

        app(LegalHoldService::class)->set($this->record, 'Sengketa aktif.', $this->adminOne);
        $this->assertFalse($retention->canArchive($this->record->fresh()));

        try {
            $retention->archive($this->record->fresh(), $this->adminTwo);
            $this->fail('Arsip saat legal hold seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('RETENTION_LEGAL_HOLD', $e->errorCode);
        }

        $this->record->forceFill(['legal_hold' => false])->save();

        $archived = $retention->archive($this->record->fresh(), $this->adminTwo);
        $this->assertSame(MedicalRecord::STATUS_ARCHIVED, $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'medical_record.archived',
            'model_id' => $archived->id,
            'result' => 'allowed',
        ]);
    }

    public function test_legal_hold_release_requires_second_admin(): void
    {
        $service = app(LegalHoldService::class);

        try {
            $service->set($this->record, '', $this->adminOne);
            $this->fail('Legal hold tanpa alasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LEGAL_HOLD_REASON_REQUIRED', $e->errorCode);
        }

        $held = $service->set($this->record, 'Permintaan pengadilan.', $this->adminOne);

        $this->assertTrue($held->legal_hold);
        $this->assertSame('Permintaan pengadilan.', $held->legal_hold_reason);
        $this->assertSame($this->adminOne->id, $held->legal_hold_set_by);
        $this->assertNotNull($held->legal_hold_set_at);

        try {
            $service->requestRelease($held, 'Sengketa selesai.', $this->doctorUser);
            $this->fail('Dokter tidak boleh melepas legal hold.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LEGAL_HOLD_NOT_AUTHORIZED', $e->errorCode);
        }

        $requested = $service->requestRelease($held, 'Sengketa selesai.', $this->adminOne);
        $this->assertTrue($requested->legal_hold);
        $this->assertNotNull($requested->legal_hold_release_requested_at);
        $this->assertSame($this->adminOne->id, $requested->legal_hold_release_requested_by);

        try {
            $service->approveRelease($requested, $this->adminOne);
            $this->fail('Pemohon tidak boleh menyetujui pelepasan sendiri.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('LEGAL_HOLD_RELEASE_DUAL_APPROVAL_REQUIRED', $e->errorCode);
        }

        $released = $service->approveRelease($requested, $this->adminTwo);

        $this->assertFalse($released->legal_hold);
        $this->assertNotNull($released->legal_hold_release_approved_at);
        $this->assertSame($this->adminTwo->id, $released->legal_hold_release_approved_by);
        $this->assertSame(1, AuditLog::where('action', 'medical_record.legal_hold.set')->where('model_id', $released->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'medical_record.legal_hold.release_approved')->where('model_id', $released->id)->count());
    }

    public function test_retention_and_privacy_commands_are_safe_read_only_checks(): void
    {
        $this->record->forceFill([
            'status' => MedicalRecord::STATUS_FINAL,
            'retention_until' => null,
            'created_at' => now()->subYears(6),
            'extra_payment_status' => 'not_required',
        ])->save();

        $this->artisan('medical-records:refresh-retention')
            ->expectsOutputToContain('retention_until diperbarui:')
            ->assertSuccessful();

        $this->assertNotNull($this->record->fresh()->retention_until);

        PrivacyRequest::create([
            'user_id' => $this->owner->id,
            'clinic_id' => $this->clinic->id,
            'request_type' => PrivacyRequest::TYPE_ACCESS,
            'status' => PrivacyRequest::STATUS_RECEIVED,
            'description' => 'Lewat SLA.',
            'requested_at' => now()->subDays(20),
            'due_at' => now()->subDays(6),
        ]);

        $this->artisan('privacy:list-overdue')
            ->expectsOutputToContain('Permintaan PDP lewat SLA: 1')
            ->assertSuccessful();
    }
}
