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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aturan tata kelola dokumen rekam medis.
 *
 * Yang dijaga di sini adalah hal-hal yang tidak boleh longgar demi
 * pertanggungjawaban medis: satu booking satu rekam medis, pembatalan tidak
 * menghapus data, dan lampiran hanya bisa diunduh lewat tautan bertanda tangan.
 */
class MedicalRecordGovernanceTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;

    private User $owner;

    private Doctor $doctor;

    private Pet $pet;

    private Booking $booking;

    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $suffix = Str::lower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Governance Clinic ' . $suffix,
            'slug' => 'governance-' . $suffix,
            'address' => 'Jl. Governance',
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'govowner', 'email' => 'govowner' . $suffix . '@gov.test',
            'password' => Hash::make('password123'), 'role' => 'user',
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Gov ' . $suffix, 'specialization' => 'Umum',
            'is_active' => true, 'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'], 'start_time' => '08:00:00', 'end_time' => '17:00:00',
        ]);

        $service = Service::create([
            'name' => 'Layanan Gov ' . $suffix, 'price' => 100000,
            'is_active' => true, 'clinic_id' => $this->clinic->id,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->owner->id, 'name' => 'GovPet', 'species' => 'Cat', 'gender' => 'male',
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->owner->id, 'pet_id' => $this->pet->id, 'doctor_id' => $this->doctor->id,
            'service_id' => $service->id, 'clinic_id' => $this->clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00', 'status' => 'completed',
            'payment_status' => 'paid', 'total_amount' => 100000,
            'paid_amount' => 100000, 'remaining_amount' => 0,
        ]);

        $this->record = MedicalRecord::create([
            'booking_id' => $this->booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Otitis externa',
            'treatment' => 'Bersihkan telinga, tetes antibiotik',
        ]);
    }

    public function test_one_booking_can_only_have_one_medical_record(): void
    {
        $this->expectException(QueryException::class);

        MedicalRecord::create([
            'booking_id' => $this->booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Duplikat',
            'treatment' => 'Duplikat',
        ]);
    }

    public function test_voiding_keeps_the_record_and_its_content(): void
    {
        $this->record->void('Salah input pasien', $this->owner->id);

        $fresh = $this->record->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(MedicalRecord::STATUS_VOID, $fresh->status);
        $this->assertSame('Salah input pasien', $fresh->void_reason);
        $this->assertNotNull($fresh->voided_at);
        $this->assertSame('Otitis externa', $fresh->diagnosis);
    }

    public function test_attachment_is_only_reachable_through_a_signed_url(): void
    {
        $attachment = MedicalRecordAttachment::create([
            'medical_record_id' => $this->record->id,
            'path' => 'medical-records/' . $this->record->id . '/hasil.pdf',
            'original_name' => 'hasil.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => $this->owner->id,
        ]);

        Storage::disk('local')->put($attachment->path, '%PDF-1.4 test');

        $url = URL::temporarySignedRoute(
            'medical-records.attachments.download',
            now()->addMinutes(5),
            ['record' => $this->record->id, 'attachment' => $attachment->id]
        );

        $this->get($url)->assertOk();

        $this->get(route('medical-records.attachments.download', [
            'record' => $this->record->id,
            'attachment' => $attachment->id,
        ]))->assertForbidden();
    }

    public function test_content_hash_changes_when_clinical_content_changes(): void
    {
        $first = $this->record->computeContentHash();

        $this->record->update(['diagnosis' => 'Otitis media']);

        $this->assertNotSame($first, $this->record->fresh()->computeContentHash());
    }
}
