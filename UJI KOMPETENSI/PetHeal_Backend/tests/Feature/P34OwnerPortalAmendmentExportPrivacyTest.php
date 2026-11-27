<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\MedicalRecordExport;
use App\Models\MedicalRecordVersion;
use App\Models\Pet;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\MedicalRecordLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3.4 — portal pemilik: amendment, salinan PDF, dan permintaan PDP.
 *
 * Endpoint baru bersifat additive. Kontrak /api/medical-records tidak berubah.
 */
class P34OwnerPortalAmendmentExportPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $owner;
    private User $otherOwner;
    private User $doctorUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        config()->set('medical_records.lifecycle_enforcement_enabled', false);
        config()->set('medical_records.credential_gate_enabled', false);

        $this->clinic = Clinic::create([
            'name' => 'P34 ' . $suffix,
            'slug' => 'p34-' . $suffix,
            'address' => 'Jl. P34',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner P34',
            'email' => 'owner' . $suffix . '@p34.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->otherOwner = User::create([
            'name' => 'Owner Lain P34',
            'email' => 'other' . $suffix . '@p34.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P34',
            'email' => 'doctor' . $suffix . '@p34.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P34 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'P34Pet',
            'species' => 'Dog',
            'breed' => 'Beagle',
            'gender' => 'male',
            'weight' => 10,
        ]);

        $this->record = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'status' => MedicalRecord::STATUS_DRAFT,
            'subjective' => 'Batuk',
            'objective' => 'Demam ringan',
            'assessment' => 'Infeksi saluran napas',
            'plan' => 'Antibiotik dan kontrol',
            'diagnosis' => 'Bronkitis',
            'treatment' => 'Terapi suportif',
            'medicine' => 'Amoksisilin',
            'notes' => 'Pantau napas.',
            'extra_payment_status' => 'not_required',
        ]);

        app(MedicalRecordLifecycleService::class)->finalize($this->record, $this->doctorUser);
        $this->record->refresh();
    }

    public function test_owner_sees_only_whitelisted_amendment_summary(): void
    {
        $version = MedicalRecordVersion::where('medical_record_id', $this->record->id)->firstOrFail();

        $amendment = MedicalRecordAmendment::create([
            'clinic_id' => $this->clinic->id,
            'medical_record_id' => $this->record->id,
            'base_version_id' => $version->id,
            'amendment_type' => MedicalRecordAmendment::TYPE_CLINICAL,
            'requested_by' => $this->doctorUser->id,
            'requested_at' => now()->subDay(),
            'reason' => 'RALASIA_REASON',
            'before' => ['diagnosis' => 'RAHASIA_BEFORE'],
            'after' => ['diagnosis' => 'RAHASIA_AFTER'],
            'status' => MedicalRecordAmendment::STATUS_APPLIED,
            'reviewed_by' => $this->doctorUser->id,
            'reviewed_at' => now()->subHours(20),
            'review_notes' => 'RALASIA_REVIEW',
            'reviewer_credential_snapshot' => ['sip' => 'RAHASIA_SIP'],
            'applied_version_id' => $version->id,
            'applied_at' => now()->subHours(18),
            'signature_path' => 'private/RAHASIA_SIGNATURE.pdf',
        ]);

        $response = $this->apiAs($this->owner)
            ->getJson('/api/medical-records/' . $this->record->id . '/amendments')
            ->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $amendment->id)
            ->assertJsonPath('data.0.type', MedicalRecordAmendment::TYPE_CLINICAL)
            ->assertJsonPath('data.0.status', MedicalRecordAmendment::STATUS_APPLIED)
            ->assertJsonPath('data.0.applied_version_no', 1)
            ->assertJsonMissingPath('data.0.before')
            ->assertJsonMissingPath('data.0.after')
            ->assertJsonMissingPath('data.0.reason')
            ->assertJsonMissingPath('data.0.review_notes')
            ->assertJsonMissingPath('data.0.reviewer_credential_snapshot')
            ->assertJsonMissingPath('data.0.signature_path');

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('RAHASIA_', $payload);

        $this->apiAs($this->otherOwner)
            ->getJson('/api/medical-records/' . $this->record->id . '/amendments')
            ->assertNotFound();
    }

    public function test_owner_can_create_single_use_pdf_export_and_download_it_once(): void
    {
        $created = $this->apiAs($this->owner)
            ->getJson('/api/medical-records/' . $this->record->id . '/export?format=pdf')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', MedicalRecordExport::FORMAT_PDF)
            ->assertJsonPath('data.purpose', MedicalRecordExport::PURPOSE_OWNER_COPY);

        $token = $created->json('data.token');
        $exportId = $created->json('data.export_id');

        $this->assertIsString($token);
        $this->assertGreaterThanOrEqual(32, strlen($token));
        $this->assertStringContainsString('/api/medical-record-exports/', $created->json('data.download_url'));

        $export = MedicalRecordExport::findOrFail($exportId);
        $this->assertTrue(Storage::disk('local')->exists($export->rendered_path));
        $this->assertSame(hash('sha256', Storage::disk('local')->get($export->rendered_path)), $export->rendered_hash);
        $this->assertSame(64, strlen($export->content_hash));

        $download = $this->apiAs($this->owner)
            ->get('/api/medical-record-exports/' . $token)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $download->streamedContent() ?: $download->getContent());

        $this->apiAs($this->owner)
            ->getJson('/api/medical-record-exports/' . $token)
            ->assertStatus(410)
            ->assertJsonPath('code', 'EXPORT_TOKEN_ALREADY_USED');

        Storage::disk('local')->delete($export->rendered_path);
    }

    public function test_export_rejects_non_owner_draft_and_unsupported_format(): void
    {
        $this->apiAs($this->otherOwner)
            ->getJson('/api/medical-records/' . $this->record->id . '/export')
            ->assertNotFound();

        $this->apiAs($this->owner)
            ->getJson('/api/medical-records/' . $this->record->id . '/export?format=csv')
            ->assertStatus(422)
            ->assertJsonPath('code', 'OWNER_EXPORT_FORMAT_UNSUPPORTED');

        $draft = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'status' => MedicalRecord::STATUS_DRAFT,
            'diagnosis' => 'Draft',
            'treatment' => 'Belum final',
        ]);

        $this->apiAs($this->owner)
            ->getJson('/api/medical-records/' . $draft->id . '/export')
            ->assertStatus(409)
            ->assertJsonPath('code', 'RECORD_NOT_FINAL');
    }

    public function test_owner_privacy_request_api_is_self_scoped_and_whitelisted(): void
    {
        $created = $this->apiAs($this->owner)
            ->postJson('/api/privacy-requests', [
                'request_type' => PrivacyRequest::TYPE_ACCESS,
                'description' => 'Saya ingin salinan catatan medis.',
                'clinic_id' => $this->clinic->id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_type', PrivacyRequest::TYPE_ACCESS)
            ->assertJsonPath('data.status', PrivacyRequest::STATUS_RECEIVED)
            ->assertJsonPath('data.description', 'Saya ingin salinan catatan medis.')
            ->assertJsonPath('data.is_overdue', false);

        $created->assertJsonMissingPath('data.handled_by')
            ->assertJsonMissingPath('data.response_path')
            ->assertJsonMissingPath('data.user_id');

        $id = $created->json('data.id');
        $this->assertNotNull(PrivacyRequest::find($id));

        $this->apiAs($this->otherOwner)
            ->postJson('/api/privacy-requests', [
                'request_type' => 'bukan_tipe',
                'description' => 'Tes',
            ])
            ->assertStatus(422);

        $list = $this->apiAs($this->owner)
            ->getJson('/api/privacy-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1);

        $this->assertSame($id, $list->json('data.0.id'));

        $this->apiAs($this->otherOwner)
            ->getJson('/api/privacy-requests')
            ->assertOk()
            ->assertJsonPath('pagination.total', 0);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('p34-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }
}
