<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Doctor;
use App\Models\InpatientLocationLog;
use App\Models\InpatientStay;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InpatientService
{
    public function admit(MedicalRecord $record, array $data, User $actor): InpatientStay
    {
        $this->assertRecordClinic($record, $actor);

        if (! $record->encounter_id) {
            throw new MedicalRecordLifecycleException('INPATIENT_ENCOUNTER_REQUIRED', 'Rawat inap memerlukan encounter klinis.', 422);
        }

        if (InpatientStay::where('encounter_id', $record->encounter_id)->exists()) {
            throw new MedicalRecordLifecycleException('INPATIENT_ALREADY_ADMITTED', 'Encounter ini sudah memiliki rawat inap.', 422);
        }

        $doctorId = $data['admitting_doctor_id'] ?? $record->doctor_id;

        if ($doctorId) {
            $doctor = Doctor::whereKey($doctorId)->where('clinic_id', $record->clinic_id)->first();

            if (! $doctor) {
                throw new MedicalRecordLifecycleException('INPATIENT_DOCTOR_MISMATCH', 'Dokter rawat inap tidak berada di klinik ini.', 404);
            }
        }

        return DB::transaction(function () use ($record, $data, $actor, $doctorId) {
            $ward = $this->text($data['ward'] ?? null, 60);
            $kennel = $this->text($data['kennel_code'] ?? null, 40);
            $admittedAt = ! empty($data['admitted_at'])
                ? \Illuminate\Support\Carbon::parse($data['admitted_at'])
                : now();

            $stay = InpatientStay::create([
                'clinic_id' => $record->clinic_id,
                'encounter_id' => $record->encounter_id,
                'medical_record_id' => $record->id,
                'admitted_at' => $admittedAt,
                'kennel_code' => $kennel,
                'ward' => $ward,
                'status' => InpatientStay::STATUS_ADMITTED,
                'admitting_doctor_id' => $doctorId,
            ])->refresh();

            InpatientLocationLog::create([
                'clinic_id' => $record->clinic_id,
                'inpatient_stay_id' => $stay->id,
                'to_ward' => $ward,
                'to_kennel_code' => $kennel,
                'moved_at' => $admittedAt,
                'moved_by' => $actor->id,
                'reason' => 'Admisi rawat inap',
            ]);

            return $stay;
        });
    }

    public function transfer(InpatientStay $stay, array $data, User $actor): InpatientStay
    {
        $this->assertRowClinic($stay, $actor);
        $this->assertActive($stay);

        $ward = $this->text($data['ward'] ?? null, 60);
        $kennel = $this->text($data['kennel_code'] ?? null, 40);

        if (($ward ?? '') === '' && ($kennel ?? '') === '') {
            throw new MedicalRecordLifecycleException('INPATIENT_LOCATION_REQUIRED', 'Lokasi baru wajib diisi.', 422);
        }

        return DB::transaction(function () use ($stay, $actor, $ward, $kennel, $data) {
            InpatientLocationLog::create([
                'clinic_id' => $stay->clinic_id,
                'inpatient_stay_id' => $stay->id,
                'from_ward' => $stay->ward,
                'from_kennel_code' => $stay->kennel_code,
                'to_ward' => $ward,
                'to_kennel_code' => $kennel,
                'moved_at' => now(),
                'moved_by' => $actor->id,
                'reason' => $this->text($data['reason'] ?? null, 255),
            ]);

            $stay->forceFill([
                'ward' => $ward ?? $stay->ward,
                'kennel_code' => $kennel ?? $stay->kennel_code,
                'status' => InpatientStay::STATUS_IN_TREATMENT,
            ])->save();

            return $stay->refresh();
        });
    }

    public function discharge(InpatientStay $stay, array $data, User $actor): InpatientStay
    {
        $this->assertRowClinic($stay, $actor);
        $this->assertActive($stay);

        $summary = trim((string) ($data['discharge_summary'] ?? ''));
        $instructions = trim((string) ($data['discharge_instructions'] ?? ''));

        if ($summary === '') {
            throw new MedicalRecordLifecycleException('INPATIENT_SUMMARY_REQUIRED', 'Ringkasan pulang wajib diisi.', 422);
        }

        $stay->forceFill([
            'status' => InpatientStay::STATUS_DISCHARGED,
            'discharged_at' => now(),
            'discharge_summary' => mb_substr($summary, 0, 20000),
            'discharge_instructions' => $instructions === '' ? null : mb_substr($instructions, 0, 20000),
        ])->save();

        return $stay->refresh();
    }

    public function markDied(InpatientStay $stay, array $data, User $actor): InpatientStay
    {
        $this->assertRowClinic($stay, $actor);
        $this->assertActive($stay);

        $summary = trim((string) ($data['discharge_summary'] ?? ''));

        if ($summary === '') {
            throw new MedicalRecordLifecycleException('INPATIENT_SUMMARY_REQUIRED', 'Ringkasan akhir wajib diisi.', 422);
        }

        $stay->forceFill([
            'status' => InpatientStay::STATUS_DIED,
            'discharged_at' => now(),
            'discharge_summary' => mb_substr($summary, 0, 20000),
        ])->save();

        return $stay->refresh();
    }

    private function assertActive(InpatientStay $stay): void
    {
        if (in_array($stay->status, [InpatientStay::STATUS_DISCHARGED, InpatientStay::STATUS_DIED, InpatientStay::STATUS_TRANSFERRED], true)) {
            throw new MedicalRecordLifecycleException('INPATIENT_NOT_ACTIVE', 'Rawat inap sudah tidak aktif.', 422);
        }
    }

    private function assertRecordClinic(MedicalRecord $record, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $record->clinic_id) {
            throw new MedicalRecordLifecycleException('INPATIENT_CLINIC_MISMATCH', 'Rawat inap hanya boleh diakses pada klinik yang sama.', 404);
        }
    }

    private function assertRowClinic(InpatientStay $stay, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $stay->clinic_id) {
            throw new MedicalRecordLifecycleException('INPATIENT_CLINIC_MISMATCH', 'Rawat inap hanya boleh diakses pada klinik yang sama.', 404);
        }
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
