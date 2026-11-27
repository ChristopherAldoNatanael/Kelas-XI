<?php

namespace Tests\Feature;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\DoseReference;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineStockMovement;
use App\Models\Pet;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Services\DispensationService;
use App\Services\MedicineStockService;
use App\Services\PrescriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1.2 — resep, dispensing, registrasi obat, dosis, dan reversal.
 */
class P12PharmacyDispensingTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private Doctor $doctor;
    private Pet $pet;
    private MedicalRecord $record;
    private Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P12 ' . $suffix,
            'slug' => 'p12-' . $suffix,
            'address' => 'Jl. P12',
            'is_active' => true,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dokter P12',
            'email' => 'doctor' . $suffix . '@p12.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'user_id' => $this->doctorUser->id,
            'name' => 'Dr P12 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $owner = User::create([
            'name' => 'Owner P12',
            'email' => 'owner' . $suffix . '@p12.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P12Pet',
            'species' => 'Cat',
            'weight' => 4,
            'gender' => 'male',
        ]);

        $this->record = MedicalRecord::create([
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Infeksi',
            'treatment' => 'Antibiotik',
        ]);

        $this->medicine = Medicine::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Amoxicillin P12 ' . $suffix,
            'unit' => 'tablet',
            'price' => 2500,
            'registration_status' => Medicine::REGISTRATION_REGISTERED,
            'registration_number' => 'REG-P12-' . strtoupper($suffix),
            'registration_expires_at' => now()->addYear()->toDateString(),
        ]);

        app(MedicineStockService::class)->setOpeningStock($this->medicine, 10);
    }

    public function test_dispensing_is_idempotent_and_does_not_double_deduct(): void
    {
        $prescription = $this->prescription(5);
        $service = app(DispensationService::class);

        $first = $service->dispense(
            $prescription,
            [['prescription_item_id' => $prescription->items->first()->id]],
            'DISP-KEY-1',
            $this->doctorUser
        );

        $this->assertSame(Dispensation::STATUS_COMPLETED, $first->status);
        $this->assertSame(5.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(1, $prescription->items->first()->fresh()->dispensationItems()->count());
        $this->assertSame(1, DispensationItem::where('dispensation_id', $first->id)->count());
        $this->assertSame(1, MedicineStockMovement::where('dispensation_item_id', $first->items->first()->id)->count());

        $second = $service->dispense(
            $prescription,
            [['prescription_item_id' => $prescription->items->first()->id]],
            'DISP-KEY-1',
            $this->doctorUser
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(5.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(1, Dispensation::where('prescription_id', $prescription->id)->count());

        $this->assertSame(Prescription::STATUS_DISPENSED, $prescription->fresh()->status);

        try {
            $service->dispense(
                $prescription,
                [['prescription_item_id' => $prescription->items->first()->id]],
                'DISP-KEY-2',
                $this->doctorUser
            );
            $this->fail('Dispensing ulang melebihi sisa seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('DISPENSATION_EXCEEDS_REMAINING', $e->errorCode);
        }
    }

    public function test_reversal_uses_original_batch_and_is_idempotent(): void
    {
        $prescription = $this->prescription(5);
        $service = app(DispensationService::class);

        $dispensation = $service->dispense(
            $prescription,
            [['prescription_item_id' => $prescription->items->first()->id, 'quantity' => 3]],
            'DISP-KEY-3',
            $this->doctorUser
        );

        $this->assertSame(7.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(Prescription::STATUS_PARTIALLY_DISPENSED, $prescription->fresh()->status);

        $reversal = $service->reverse($dispensation, [], 'REV-KEY-3', 'Obat dikembalikan.', $this->doctorUser);

        $this->assertSame(Dispensation::KIND_REVERSAL, $reversal->kind);
        $this->assertSame(10.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(Dispensation::STATUS_REVERSED, $dispensation->fresh()->status);
        $this->assertSame($dispensation->items->first()->batch_id, $reversal->items->first()->original_batch_id);

        $again = $service->reverse($dispensation, [], 'REV-KEY-3', 'Retry.', $this->doctorUser);
        $this->assertSame($reversal->id, $again->id);
        $this->assertSame(10.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(1, Dispensation::where('kind', Dispensation::KIND_REVERSAL)->count());
    }

    public function test_registration_status_blocks_dispensing_unless_doctor_override(): void
    {
        $prescription = $this->prescription(1);
        $service = app(DispensationService::class);

        $this->medicine->forceFill(['registration_status' => Medicine::REGISTRATION_NOT_REGISTERED])->save();

        try {
            $service->dispense(
                $prescription,
                [['prescription_item_id' => $prescription->items->first()->id]],
                'DISP-NOT-REG',
                $this->doctorUser
            );
            $this->fail('Obat not_registered seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('MEDICINE_NOT_ELIGIBLE', $e->errorCode);
        }

        $this->medicine->forceFill(['registration_status' => Medicine::REGISTRATION_UNKNOWN])->save();

        $ok = $service->dispense(
            $prescription,
            [[
                'prescription_item_id' => $prescription->items->first()->id,
                'eligibility_override_reason' => 'Dasar hukum dan bukti dokter tercatat.',
            ]],
            'DISP-UNKNOWN-OVERRIDE',
            $this->doctorUser
        );

        $this->assertSame(Medicine::REGISTRATION_UNKNOWN, $ok->items->first()->registration_status_snapshot);
        $this->assertNotNull($ok->items->first()->eligibility_override_reason);

        $this->medicine->forceFill([
            'registration_status' => Medicine::REGISTRATION_REGISTERED,
            'registration_expires_at' => now()->subDay()->toDateString(),
        ])->save();

        try {
            $service->dispense(
                $this->prescription(1),
                [['prescription_item_id' => PrescriptionItem::where('medical_record_id', $this->record->id)->latest('id')->first()->id]],
                'DISP-EXPIRED-REG',
                $this->doctorUser
            );
            $this->fail('Registrasi kedaluwarsa seharusnya ditolak.');
        } catch (MedicalRecordLifecycleException $e) {
            $this->assertSame('MEDICINE_NOT_ELIGIBLE', $e->errorCode);
        }
    }

    public function test_dose_check_is_non_blocking_and_records_override_reason(): void
    {
        DoseReference::create([
            'clinic_id' => $this->clinic->id,
            'species' => 'Cat',
            'medicine_name' => $this->medicine->name,
            'route' => 'oral',
            'dose_min' => 5,
            'dose_max' => 10,
            'dose_unit' => 'mg/kg',
            'dose_basis' => 'mg_per_kg',
            'source' => 'Formularium Klinik P12',
            'is_active' => true,
        ]);

        $prescription = $this->prescription(5);
        $items = app(PrescriptionService::class)->syncForRecord($this->record, [[
            'medicine_id' => $this->medicine->id,
            'quantity' => 5,
            'route' => 'oral',
            'dose_value' => 20,
            'dose_unit' => 'mg/kg',
            'dose_basis' => 'mg_per_kg',
        ]]);

        $this->assertSame(12500.0, $items);
        $item = $this->record->prescriptionItems()->firstOrFail();
        $this->assertSame('warning', $item->dose_check_status);
        $this->assertSame('80.0000', (string) $item->calculated_dose);
        $this->assertNotNull($item->dose_reference_id);
        $this->assertSame(Prescription::STATUS_DISPENSED, Prescription::where('medical_record_id', $this->record->id)->first()->status);

        app(PrescriptionService::class)->syncForRecord($this->record, [[
            'medicine_id' => $this->medicine->id,
            'quantity' => 5,
            'route' => 'oral',
            'dose_value' => 20,
            'dose_unit' => 'mg/kg',
            'dose_basis' => 'mg_per_kg',
            'dose_check_notes' => 'Alasan klinis: infeksi berat, dipantau.',
        ]]);

        $item = $this->record->prescriptionItems()->firstOrFail();
        $this->assertSame('overridden', $item->dose_check_status);
        $this->assertStringContainsString('infeksi berat', $item->dose_check_notes);
    }

    private function prescription(float $quantity): Prescription
    {
        $suffix = strtolower(Str::random(4));

        $prescription = Prescription::create([
            'clinic_id' => $this->clinic->id,
            'medical_record_id' => $this->record->id,
            'prescription_number' => 'RX-P12-' . strtoupper($suffix),
            'prescribed_by' => $this->doctorUser->id,
            'prescribed_at' => now(),
            'status' => Prescription::STATUS_ISSUED,
        ]);

        PrescriptionItem::create([
            'medical_record_id' => $this->record->id,
            'prescription_id' => $prescription->id,
            'medicine_id' => $this->medicine->id,
            'medicine_name' => $this->medicine->name,
            'quantity' => $quantity,
            'total_quantity' => $quantity,
            'unit' => $this->medicine->unit,
            'unit_price' => $this->medicine->price,
            'route' => 'oral',
            'dose' => '2x1',
        ]);

        return $prescription->load('items');
    }
}
