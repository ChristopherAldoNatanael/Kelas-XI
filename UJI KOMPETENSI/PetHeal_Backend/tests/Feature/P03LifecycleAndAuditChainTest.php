<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\MedicalRecordRevision;
use App\Models\MedicalRecordVersion;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditChainService;
use App\Services\MedicalRecordAmendmentService;
use App\Services\MedicalRecordLifecycleService;
use App\Services\MedicalRecordRevisionService;
use App\Services\MedicalRecordVersionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0.3 — lifecycle final, versi immutable, amendment, dan hash chain.
 */
class P03LifecycleAndAuditChainTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private User $doctorUser;
    private Pet $pet;
    private Doctor $doctor;
    private Booking $booking;
    private MedicalRecordLifecycleService $lifecycle;
    private MedicalRecordAmendmentService $amendments;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P03 ' . $suffix,
            'slug' => 'p03-' . $suffix,
            'address' => 'Jl. P03',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin P03',
            'email' => 'admin' . $suffix . '@p03.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P03',
            'email' => 'doctor' . $suffix . '@p03.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr P03 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P03',
            'email' => 'owner' . $suffix . '@p03.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P03Pet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $service = Service::create([
            'name' => 'Layanan P03 ' . $suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);

        $this->booking = Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $service->id,
            'clinic_id' => $this->clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '10:00:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);

        $this->lifecycle = app(MedicalRecordLifecycleService::class);
        $this->amendments = app(MedicalRecordAmendmentService::class);

        config()->set('medical_records.lifecycle_enforcement_enabled', false);
        config()->set('medical_records.amendments_enabled', false);
    }

    public function test_finalization_creates_immutable_version_and_is_idempotent(): void
    {
        $record = $this->record();

        $version = $this->lifecycle->finalize($record, $this->admin);

        $record->refresh();

        $this->assertSame(MedicalRecord::STATUS_FINAL, $record->status);
        $this->assertSame(1, $version->version_no);
        $this->assertSame($version->id, $record->current_version_id);
        $this->assertSame(1, $record->version);
        $this->assertSame(64, strlen($record->content_hash));
        $this->assertSame($record->content_hash, $version->content_hash);
        $this->assertSame('Otitis externa', $version->snapshot['diagnosis']);

        $again = $this->lifecycle->finalize($record, $this->admin);

        $this->assertSame($version->id, $again->id);
        $this->assertSame(1, MedicalRecordVersion::where('medical_record_id', $record->id)->count());
        $this->assertSame(1, $record->refresh()->version);
    }

    public function test_admin_route_creates_draft_when_enforcement_is_on(): void
    {
        config()->set('medical_records.lifecycle_enforcement_enabled', true);

        $this->actingAs($this->admin)
            ->post('/admin/medical-records', [
                'booking_id' => $this->booking->id,
                'diagnosis' => 'Flu',
                'treatment' => 'Istirahat',
            ])
            ->assertRedirect(route('admin.bookings.index'));

        $record = MedicalRecord::where('booking_id', $this->booking->id)->firstOrFail();

        $this->assertSame(MedicalRecord::STATUS_DRAFT, $record->status);
        $this->assertNull($record->current_version_id);
        $this->assertNull($record->finalized_at);
    }

    public function test_admin_cannot_finalize_when_enforcement_is_on(): void
    {
        config()->set('medical_records.lifecycle_enforcement_enabled', true);

        $record = $this->record();

        try {
            $this->lifecycle->finalize($record, $this->admin);
            $this->fail('Finalisasi admin seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('ADMIN_CANNOT_FINALIZE', $e->errorCode);
            $this->assertSame(403, $e->httpStatus);
        }

        $this->assertSame(0, MedicalRecordVersion::where('medical_record_id', $record->id)->count());
        $this->assertSame(MedicalRecord::STATUS_DRAFT, $record->refresh()->status);

        $version = $this->lifecycle->finalize($record, $this->doctorUser);

        $this->assertSame(1, $version->version_no);
        $this->assertSame(MedicalRecord::STATUS_FINAL, $record->refresh()->status);
    }

    public function test_lifecycle_can_enter_review_and_return_to_draft_but_final_is_immutable(): void
    {
        $record = $this->record();

        $this->lifecycle->markInReview($record, $this->doctorUser);
        $this->assertSame(MedicalRecord::STATUS_IN_REVIEW, $record->refresh()->status);

        $this->lifecycle->returnToDraft($record);
        $this->assertSame(MedicalRecord::STATUS_DRAFT, $record->refresh()->status);
        $this->assertNull($record->in_review_at);

        $this->lifecycle->finalize($record, $this->doctorUser);

        $this->expectException(MedicalRecordLifecycleException::class);
        $this->lifecycle->returnToDraft($record->refresh());
    }

    public function test_amendment_request_approve_apply_creates_second_version(): void
    {
        config()->set('medical_records.amendments_enabled', true);

        $record = $this->record();
        $this->lifecycle->finalize($record, $this->doctorUser);

        $amendment = $this->amendments->request(
            $record->refresh(),
            $this->admin,
            MedicalRecordAmendment::TYPE_CLINICAL,
            'Hasil lab mengubah diagnosis.',
            ['diagnosis' => 'Otitis externa bakterial', 'plan' => 'Antibiotik 7 hari']
        );

        $this->assertSame(MedicalRecordAmendment::STATUS_REQUESTED, $amendment->status);
        $this->assertSame((string) $record->id, $amendment->pending_key);

        try {
            $this->amendments->request(
                $record->refresh(),
                $this->admin,
                MedicalRecordAmendment::TYPE_CLINICAL,
                'Duplikat',
                ['diagnosis' => 'X']
            );
            $this->fail('Amendment kedua seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('AMENDMENT_ALREADY_PENDING', $e->errorCode);
        }

        $approved = $this->amendments->approve($amendment, $this->doctorUser, 'Sesuai hasil lab.');
        $this->assertSame(MedicalRecordAmendment::STATUS_APPROVED, $approved->status);

        $applied = $this->amendments->apply($approved, $this->doctorUser);
        $record->refresh();

        $this->assertSame(MedicalRecordAmendment::STATUS_APPLIED, $applied->status);
        $this->assertNull($applied->pending_key);
        $this->assertSame(2, $record->version);
        $this->assertSame(2, MedicalRecordVersion::where('medical_record_id', $record->id)->count());
        $this->assertSame('Otitis externa bakterial', $record->diagnosis);
        $this->assertSame('Antibiotik 7 hari', $record->plan);
        $this->assertSame($record->content_hash, $record->currentVersion->content_hash);
        $this->assertSame($applied->id, $record->currentVersion->amendment_id);
    }

    public function test_amendment_cannot_be_applied_when_base_version_is_stale(): void
    {
        config()->set('medical_records.amendments_enabled', true);

        $record = $this->record();
        $this->lifecycle->finalize($record, $this->doctorUser);

        $amendment = $this->amendments->request(
            $record->refresh(),
            $this->admin,
            MedicalRecordAmendment::TYPE_CLINICAL,
            'Koreksi.',
            ['diagnosis' => 'Koreksi']
        );

        $this->amendments->approve($amendment, $this->doctorUser);

        app(MedicalRecordVersionService::class)->createVersion($record->refresh(), $this->doctorUser->id);

        try {
            $this->amendments->apply($amendment->refresh(), $this->doctorUser);
            $this->fail('Amendment basi seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('STALE_VERSION', $e->errorCode);
        }
    }

    public function test_revision_hash_chain_verifies_and_database_rejects_manipulation(): void
    {
        $record = $this->record();
        $revisions = app(MedicalRecordRevisionService::class);

        $first = $revisions->record($record, 'create', [], ['diagnosis' => 'Awal'], 'Buat', $this->doctorUser->id);
        $second = $revisions->record($record, 'update', ['diagnosis' => 'Awal'], ['diagnosis' => 'Koreksi'], 'Koreksi', $this->doctorUser->id);

        $this->assertSame(AuditChainService::GENESIS, $first->prev_hash);
        $this->assertSame($first->entry_hash, $second->prev_hash);

        $this->artisan('audit:verify-chain', ['--record' => $record->id])
            ->assertExitCode(0);

        try {
            MedicalRecordRevision::whereKey($first->id)->update(['reason' => 'diubah langsung']);
            $this->fail('UPDATE langsung seharusnya ditolak trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            MedicalRecordRevision::whereKey($first->id)->delete();
            $this->fail('DELETE langsung seharusnya ditolak trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('tidak boleh dihapus', $e->getMessage());
        }
    }

    private function record(): MedicalRecord
    {
        return MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'status' => MedicalRecord::STATUS_DRAFT,
            'subjective' => 'Nyeri telinga',
            'objective' => 'Eritema kanal',
            'assessment' => 'Otitis externa',
            'plan' => 'Bersihkan telinga',
            'diagnosis' => 'Otitis externa',
            'treatment' => 'Pembersihan',
        ]);
    }
}
