<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\ClinicalEncounter;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Encounter darurat tanpa booking.
 *
 * Service ini hanya mencatat fakta klinis. Ia tidak mengubah kredensial,
 * tidak memberi SIP/SIPP, dan tidak memfinalkan rekam medis. Dengan demikian
 * tindakan darurat tidak menjadi jalan pintas kewenangan.
 */
class EmergencyEncounterService
{
    public const TRIAGE_RED = 'red';
    public const TRIAGE_ORANGE = 'orange';
    public const TRIAGE_YELLOW = 'yellow';
    public const TRIAGE_GREEN = 'green';

    public const TRIAGE_LEVELS = [
        self::TRIAGE_RED,
        self::TRIAGE_ORANGE,
        self::TRIAGE_YELLOW,
        self::TRIAGE_GREEN,
    ];

    public function open(
        Clinic $clinic,
        Pet $pet,
        array $data,
        User $actor
    ): ClinicalEncounter {
        $this->assertClinic($clinic->id, $actor);

        if (! $pet->exists) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_PET_REQUIRED',
                'Hewan wajib diketahui untuk encounter darurat.',
                422
            );
        }

        $triage = trim((string) ($data['triage_level'] ?? ''));

        if (! in_array($triage, self::TRIAGE_LEVELS, true)) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_TRIAGE_REQUIRED',
                'Triage darurat wajib diisi: red, orange, yellow, atau green.',
                422
            );
        }

        return DB::transaction(function () use ($clinic, $pet, $data, $actor, $triage) {
            $doctorId = $data['attending_doctor_id'] ?? null;

            if (! $doctorId && $actor->role === 'doctor') {
                $doctorId = Doctor::where('user_id', $actor->id)
                    ->where('clinic_id', $clinic->id)
                    ->value('id');
            }

            if ($doctorId) {
                $doctor = Doctor::whereKey($doctorId)->where('clinic_id', $clinic->id)->first();

                if (! $doctor) {
                    throw new MedicalRecordLifecycleException(
                        'EMERGENCY_DOCTOR_MISMATCH',
                        'Dokter penanggung jawab tidak berada di klinik ini.',
                        404
                    );
                }
            }

            return ClinicalEncounter::create([
                'clinic_id' => $clinic->id,
                'pet_id' => $pet->id,
                'booking_id' => null,
                'type' => ClinicalEncounter::TYPE_EMERGENCY,
                'status' => ClinicalEncounter::STATUS_IN_PROGRESS,
                'attending_doctor_id' => $doctorId,
                'chief_complaint' => $this->text($data['chief_complaint'] ?? null),
                'triage_level' => $triage,
                'opened_at' => ! empty($data['opened_at'])
                    ? \Illuminate\Support\Carbon::parse($data['opened_at'])
                    : now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->refresh();
        });
    }

    public function updateTriage(
        ClinicalEncounter $encounter,
        string $triage,
        ?string $chiefComplaint,
        User $actor
    ): ClinicalEncounter {
        $this->assertClinic($encounter->clinic_id, $actor);

        if ($encounter->type !== ClinicalEncounter::TYPE_EMERGENCY) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_ENCOUNTER_REQUIRED',
                'Update triage hanya berlaku untuk encounter darurat.',
                422
            );
        }

        if (! in_array($triage, self::TRIAGE_LEVELS, true)) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_TRIAGE_INVALID',
                'Triage darurat tidak valid.',
                422
            );
        }

        $encounter->forceFill([
            'triage_level' => $triage,
            'chief_complaint' => $this->text($chiefComplaint) ?: $encounter->chief_complaint,
            'updated_by' => $actor->id,
        ])->save();

        return $encounter->refresh();
    }

    public function close(
        ClinicalEncounter $encounter,
        string $disposition,
        User $actor
    ): ClinicalEncounter {
        $this->assertClinic($encounter->clinic_id, $actor);

        if ($encounter->type !== ClinicalEncounter::TYPE_EMERGENCY) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_ENCOUNTER_REQUIRED',
                'Penutupan ini hanya berlaku untuk encounter darurat.',
                422
            );
        }

        $disposition = trim($disposition);

        if ($disposition === '') {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_DISPOSITION_REQUIRED',
                'Disposisi akhir encounter darurat wajib diisi.',
                422
            );
        }

        $encounter->forceFill([
            'status' => ClinicalEncounter::STATUS_COMPLETED,
            'disposition' => mb_substr($disposition, 0, 30),
            'closed_at' => now(),
            'updated_by' => $actor->id,
        ])->save();

        return $encounter->refresh();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 20000);
    }

    private function assertClinic(?int $clinicId, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $clinicId) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_CLINIC_MISMATCH',
                'Encounter darurat hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
