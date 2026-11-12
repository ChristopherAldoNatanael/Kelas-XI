<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\MedicalRecordAttachmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SOAP + lampiran rekam medis.
 *
 * Yang dikunci di sini: skema benar-benar ada, lampiran disimpan/dihapus
 * beserta filenya, dan SOP tidak bocor saat rekaman masih terkunci
 * pembayaran tambahan.
 */
class MedicalRecordSoapAndAttachmentsTest extends TestCase
{
    use DatabaseTransactions;

    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $suffix = Str::lower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'SOAP Clinic ' . $suffix,
            'slug' => 'soap-' . $suffix,
            'address' => 'Jl. SOAP',
            'is_active' => true,
        ]);

        $owner = User::create([
            'name' => 'soapowner', 'email' => 'soapowner' . $suffix . '@soap.test',
            'password' => Hash::make('password123'), 'role' => 'user',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr SOAP ' . $suffix, 'specialization' => 'Umum',
            'is_active' => true, 'clinic_id' => $clinic->id,
            'available_days' => ['monday'], 'start_time' => '08:00:00', 'end_time' => '17:00:00',
        ]);

        $service = Service::create([
            'name' => 'Layanan SOAP ' . $suffix, 'price' => 100000,
            'is_active' => true, 'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id, 'name' => 'SoapPet', 'species' => 'Cat', 'gender' => 'male',
        ]);

        $booking = Booking::create([
            'user_id' => $owner->id, 'pet_id' => $pet->id, 'doctor_id' => $doctor->id,
            'service_id' => $service->id, 'clinic_id' => $clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00', 'status' => 'completed',
            'payment_status' => 'paid', 'total_amount' => 100000,
            'paid_amount' => 100000, 'remaining_amount' => 0,
        ]);

        $this->record = MedicalRecord::create([
            'booking_id' => $booking->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'subjective' => 'Kucing muntah 2 hari',
            'objective' => 'Suhu 39,2 C',
            'assessment' => 'Suspek gastroenteritis',
            'plan' => 'Cairan IV, kontrol 3 hari',
            'diagnosis' => 'Gastroenteritis',
            'treatment' => 'Terapi cairan',
            'extra_payment_status' => 'not_required',
        ]);
    }

    public function test_soap_columns_exist(): void
    {
        foreach (['subjective', 'objective', 'assessment', 'plan'] as $column) {
            $this->assertTrue(Schema::hasColumn('medical_records', $column), "Kolom {$column} tidak ada.");
        }
        $this->assertTrue(Schema::hasTable('medical_record_attachments'));
    }

    public function test_soap_is_saved_on_the_record(): void
    {
        $fresh = $this->record->fresh();

        $this->assertSame('Kucing muntah 2 hari', $fresh->subjective);
        $this->assertSame('Suspek gastroenteritis', $fresh->assessment);
        // Field lama tetap ada — rekam medis lama tidak rusak.
        $this->assertSame('Gastroenteritis', $fresh->diagnosis);
    }

    public function test_pdf_attachment_is_stored_and_deleted_with_its_file(): void
    {
        $service = app(MedicalRecordAttachmentService::class);

        $saved = $service->storeOne(
            $this->record,
            UploadedFile::fake()->create('hasil-lab.pdf', 120, 'application/pdf'),
            null,
            0
        );

        $this->assertSame('application/pdf', $saved->mime_type);
        Storage::disk('public')->assertExists($saved->path);

        $service->delete($saved);

        Storage::disk('public')->assertMissing($saved->path);
        $this->assertNull(MedicalRecordAttachment::find($saved->id));
    }

    public function test_image_attachment_is_reencoded_to_jpeg(): void
    {
        // ImageService menulis langsung ke storage/app/public (bukan lewat
        // disk abstraction), jadi jalur nyata itu yang dipalsukan di sini.
        Storage::fake('public');
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('app/public/medical-records'));

        $service = app(MedicalRecordAttachmentService::class);

        $saved = $service->storeOne(
            $this->record,
            UploadedFile::fake()->image('rontgen.png', 400, 300),
            null,
            0
        );

        // Gambar diseragamkan jadi JPEG agar hemat ruang, dan dikenali
        // sebagai gambar oleh API (UI memakai ini untuk memilih ikon).
        $this->assertSame('image/jpeg', $saved->mime_type);
        $this->assertStringEndsWith('.jpg', $saved->path);
        $this->assertTrue($saved->isImage());
        $this->assertFileExists(storage_path('app/public/' . $saved->path));

        // Bersihkan supaya tidak meninggalkan file di storage nyata.
        @unlink(storage_path('app/public/' . $saved->path));
    }

    public function test_soap_is_masked_when_record_is_still_locked(): void
    {
        $this->record->update([
            'extra_payment_amount' => 50000,
            'extra_payment_paid_amount' => 0,
            'extra_payment_status' => 'unpaid',
        ]);

        $locked = $this->record->fresh();
        $this->assertFalse($locked->can_view_full_record);

        $locked->maskClinicalFields();

        $this->assertNull($locked->subjective);
        $this->assertNull($locked->objective);
        $this->assertNull($locked->assessment);
        $this->assertNull($locked->plan);
        $this->assertCount(0, $locked->attachments);
    }
}
