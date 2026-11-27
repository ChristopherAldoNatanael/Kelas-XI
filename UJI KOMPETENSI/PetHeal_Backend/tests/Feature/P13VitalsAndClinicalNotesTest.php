<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use App\Models\VitalMeasurement;
use App\Services\ClinicalNoteService;
use App\Services\VitalMeasurementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1.3 — riwayat vital, catatan bertanda tangan, hash, koreksi, dan shift.
 */
class P13VitalsAndClinicalNotesTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $adminUser;
    private Doctor $doctor;
    private Pet $pet;
    private ClinicalEncounter $encounter;
    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P13 ' . $suffix,
            'slug' => 'p13-' . $suffix,
            'address' => 'Jl. P13',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P13',
            'email' => 'doctor' . $suffix . '@p13.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin P13',
            'email' => 'admin' . $suffix . '@p13.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P13 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P13',
            'email' => 'owner' . $suffix . '@p13.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P13Pet',
            'species' => 'Cat',
            'weight' => 4,
            'gender' => 'male',
        ]);

        $this->encounter = ClinicalEncounter::create([
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
            'encounter_id' => $this->encounter->id,
            'diagnosis' => 'Observasi',
            'treatment' => 'Monitoring',
        ]);
    }

    public function test_multiple_vitals_are_recorded_and_legacy_snapshot_keeps_latest(): void
    {
        $service = app(VitalMeasurementService::class);

        $first = $service->recordForRecord($this->record, [
            'temperature_c' => 38.5,
            'heart_rate' => 140,
            'weight_kg' => 4.1,
            'shift' => VitalMeasurement::SHIFT_DAY,
        ], $this->doctorUser);

        $second = $service->recordForRecord($this->record, [
            'temperature_c' => 39.2,
            'heart_rate' => 156,
            'weight_kg' => 4.1,
            'shift' => VitalMeasurement::SHIFT_NIGHT,
            'notes' => 'Memburuk setelah makan.',
        ], $this->doctorUser);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, VitalMeasurement::where('medical_record_id', $this->record->id)->count());
        $this->assertSame('39.2', (string) $this->record->fresh()->vitals->temperature_c);
        $this->assertSame('Memburuk setelah makan.', $this->record->fresh()->vitals->notes);
        $this->assertSame(2, $service->seriesForRecord($this->record)->count());
        $this->assertSame($this->clinic->id, $second->clinic_id);
        $this->assertSame($this->encounter->id, $second->encounter_id);
        $this->assertSame(VitalMeasurement::SHIFT_NIGHT, $second->shift);

        $same = $service->recordForRecord($this->record, [
            'temperature_c' => 39.2,
            'heart_rate' => 156,
            'weight_kg' => 4.1,
            'notes' => 'Memburuk setelah makan.',
        ], $this->doctorUser);

        $this->assertSame($second->id, $same->id);
        $this->assertSame(2, VitalMeasurement::where('medical_record_id', $this->record->id)->count());
    }

    public function test_vital_void_requires_reason_and_is_excluded_from_series(): void
    {
        $service = app(VitalMeasurementService::class);
        $measurement = $service->recordForRecord($this->record, [
            'temperature_c' => 38.0,
            'heart_rate' => 120,
        ], $this->doctorUser);

        try {
            $service->void($measurement, '   ', $this->doctorUser);
            $this->fail('Void tanpa alasan seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('VITAL_VOID_REASON_REQUIRED', $e->errorCode);
        }

        $voided = $service->void($measurement, 'Alat ukur rusak.', $this->doctorUser);

        $this->assertTrue($voided->isVoided());
        $this->assertSame('Alat ukur rusak.', $voided->void_reason);
        $this->assertSame('38.0', (string) $voided->temperature_c);
        $this->assertSame(0, $service->seriesForRecord($this->record)->count());
        $this->assertSame(1, $service->seriesForRecord($this->record, true)->count());

        try {
            $service->void($voided, 'Lagi.', $this->doctorUser);
            $this->fail('Void ganda seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('VITAL_ALREADY_VOID', $e->errorCode);
        }
    }

    public function test_clinical_note_is_signed_hashed_and_then_immutable(): void
    {
        $service = app(ClinicalNoteService::class);

        $note = $service->create($this->encounter, [
            'note_type' => ClinicalNote::TYPE_PROGRESS,
            'subjective' => 'Nafsu makan turun.',
            'objective' => 'Suhu 39.2 C.',
            'assessment' => 'Curiga infeksi.',
            'plan' => 'Cek darah.',
        ], $this->doctorUser);

        $this->assertSame(ClinicalNote::STATUS_DRAFT, $note->status);
        $this->assertNull($note->content_hash);

        $signed = $service->sign($note, $this->doctorUser);

        $this->assertSame(ClinicalNote::STATUS_SIGNED, $signed->status);
        $this->assertNotNull($signed->signed_at);
        $this->assertSame($this->doctorUser->id, $signed->signed_by);
        $this->assertSame(64, strlen($signed->content_hash));

        try {
            $service->update($signed, ['assessment' => 'Diubah.'], $this->doctorUser);
            $this->fail('Update catatan signed seharusnya ditolak service.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CLINICAL_NOTE_NOT_DRAFT', $e->errorCode);
        }

        try {
            $signed->forceFill(['assessment' => 'Diubah langsung.'])->save();
            $this->fail('Update catatan signed seharusnya ditolak model.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak boleh diubah', $e->getMessage());
        }

        $this->assertSame('Curiga infeksi.', $signed->fresh()->assessment);
    }

    public function test_correction_is_a_new_note_and_original_stays_signed(): void
    {
        $service = app(ClinicalNoteService::class);
        $original = $service->sign($service->create($this->encounter, [
            'note_type' => ClinicalNote::TYPE_PROGRESS,
            'assessment' => 'Diagnosis awal.',
        ], $this->doctorUser), $this->doctorUser);

        $correction = $service->correct($original, [
            'assessment' => 'Koreksi: diagnosis akhir.',
            'body' => 'Hasil laboratorium sudah keluar.',
        ], $this->doctorUser);

        $this->assertSame(ClinicalNote::STATUS_DRAFT, $correction->status);
        $this->assertSame(ClinicalNote::TYPE_CORRECTION, $correction->note_type);
        $this->assertSame($original->id, $correction->correction_of_id);
        $this->assertSame('Diagnosis awal.', $original->fresh()->assessment);
        $this->assertSame(ClinicalNote::STATUS_SIGNED, $original->fresh()->status);

        $signedCorrection = $service->sign($correction, $this->doctorUser);
        $this->assertSame(ClinicalNote::STATUS_SIGNED, $signedCorrection->status);
        $this->assertSame(2, ClinicalNote::where('encounter_id', $this->encounter->id)->count());
    }

    public function test_only_doctor_may_sign_and_monitoring_notes_keep_shift(): void
    {
        $service = app(ClinicalNoteService::class);
        $note = $service->create($this->encounter, [
            'note_type' => ClinicalNote::TYPE_MONITORING,
            'body' => 'Observasi malam.',
        ], $this->doctorUser);

        try {
            $service->sign($note, $this->adminUser);
            $this->fail('Admin seharusnya tidak boleh menandatangani catatan klinis.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('CLINICAL_NOTE_SIGNER_INVALID', $e->errorCode);
        }

        $monitoring = $service->monitoring($this->encounter, [
            'shift' => ClinicalNote::SHIFT_NIGHT,
            'body' => 'Napas stabil, makan sedikit.',
        ], $this->doctorUser);

        $this->assertSame(ClinicalNote::TYPE_MONITORING, $monitoring->note_type);
        $this->assertSame(ClinicalNote::SHIFT_NIGHT, $monitoring->shift);
        $this->assertSame($this->record->id, $monitoring->medical_record_id);
    }

    public function test_database_rejects_editing_a_signed_note(): void
    {
        $service = app(ClinicalNoteService::class);
        $signed = $service->sign($service->create($this->encounter, [
            'assessment' => 'Tidak boleh berubah.',
        ], $this->doctorUser), $this->doctorUser);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('clinical_notes')->where('id', $signed->id)->update([
            'assessment' => 'Diubah lewat SQL.',
        ]);
    }

    public function test_owner_api_exposes_vital_series_inside_existing_vitals_key(): void
    {
        $service = app(VitalMeasurementService::class);
        $service->recordForRecord($this->record, ['temperature_c' => 38.1], $this->doctorUser);
        $service->recordForRecord($this->record, ['temperature_c' => 38.9], $this->doctorUser);

        $owner = $this->pet->user;
        $token = $owner->createToken('p13')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/medical-records/' . $this->record->id);

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('vitals', $data);
        $this->assertArrayHasKey('vital_series', $data['vitals']);
        $this->assertCount(2, $data['vitals']['vital_series']);
        $this->assertArrayNotHasKey('vital_series', $data);
    }
}
