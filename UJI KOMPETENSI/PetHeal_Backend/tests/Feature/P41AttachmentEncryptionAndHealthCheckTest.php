<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use App\Models\Pet;
use App\Models\User;
use App\Services\MedicalRecordAttachmentService;
use App\Services\MedicalRecordFileEncryptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.1 — enkripsi lampiran at-rest, kompatibilitas berkas lama, health check.
 */
class P41AttachmentEncryptionAndHealthCheckTest extends TestCase
{
    use DatabaseTransactions;

    private MedicalRecord $record;
    private User $owner;
    private array $cleanupPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('medical_records.attachment_encryption_enabled', true);

        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'P41 ' . $suffix,
            'slug' => 'p41-' . $suffix,
            'address' => 'Jl. P41',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner P41',
            'email' => 'owner' . $suffix . '@p41.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $doctorUser = User::create([
            'name' => 'Dokter P41',
            'email' => 'doctor' . $suffix . '@p41.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $clinic->id,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'name' => 'Dr P41 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'P41Pet',
            'species' => 'Cat',
            'gender' => 'female',
        ]);

        $this->record = MedicalRecord::create([
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'diagnosis' => 'Sehat',
            'treatment' => 'Observasi',
            'status' => MedicalRecord::STATUS_FINAL,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        parent::tearDown();
    }

    public function test_new_pdf_is_encrypted_at_rest_and_download_returns_plaintext(): void
    {
        $plaintext = "%PDF-1.4\nData medis rahasia P41\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('hasil-lab.pdf', $plaintext);

        $attachment = app(MedicalRecordAttachmentService::class)
            ->storeOne($this->record, $file, $this->owner->id);

        $this->cleanupPaths[] = $attachment->path;

        $stored = (string) Storage::disk('local')->get($attachment->path);

        $this->assertSame(1, $attachment->encryption_version);
        $this->assertTrue($attachment->isEncrypted());
        $this->assertStringStartsWith(MedicalRecordFileEncryptionService::MAGIC, $stored);
        $this->assertStringNotContainsString('rahasia P41', $stored);
        $this->assertSame(hash('sha256', $plaintext), $attachment->sha256);
        $this->assertSame($plaintext, app(MedicalRecordAttachmentService::class)->read($attachment));

        $url = \URL::temporarySignedRoute(
            'medical-records.attachments.download',
            now()->addMinutes(5),
            ['record' => $this->record->id, 'attachment' => $attachment->id]
        );

        $download = $this->get($url);
        $download->assertOk();
        $this->assertSame($plaintext, $download->getContent());
    }

    public function test_legacy_plaintext_attachment_still_reads_and_can_be_encrypted(): void
    {
        $plaintext = '%PDF-1.4 legacy plaintext P41';
        $path = 'medical-records/' . $this->record->id . '/legacy-p41.pdf';
        Storage::disk('local')->put($path, $plaintext);
        $this->cleanupPaths[] = $path;

        $legacy = MedicalRecordAttachment::create([
            'medical_record_id' => $this->record->id,
            'path' => $path,
            'original_name' => 'legacy.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($plaintext),
            'sha256' => hash('sha256', $plaintext),
            'uploaded_by' => $this->owner->id,
        ]);

        $service = app(MedicalRecordAttachmentService::class);

        $this->assertNull($legacy->encryption_version);
        $this->assertSame($plaintext, $service->read($legacy->fresh()));

        $this->artisan('medical-records:encrypt-attachments')
            ->expectsOutputToContain('Terenkripsi: 1')
            ->assertSuccessful();

        $legacy->refresh();
        $stored = (string) Storage::disk('local')->get($path);

        $this->assertSame(1, $legacy->encryption_version);
        $this->assertStringStartsWith(MedicalRecordFileEncryptionService::MAGIC, $stored);
        $this->assertSame(hash('sha256', $plaintext), $legacy->sha256);
        $this->assertSame($plaintext, $service->read($legacy));
    }

    public function test_health_check_reports_schema_triggers_audit_and_encryption(): void
    {
        $path = 'medical-records/' . $this->record->id . '/health-p41.pdf';
        Storage::disk('local')->put($path, 'plain');
        $this->cleanupPaths[] = $path;

        MedicalRecordAttachment::create([
            'medical_record_id' => $this->record->id,
            'path' => $path,
            'original_name' => 'health.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'uploaded_by' => $this->owner->id,
        ]);

        $this->artisan('medical-records:health-check --json')
            ->expectsOutputToContain('"ok": true')
            ->assertSuccessful();
    }
}
