<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\ClinicalEncounter;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use App\Models\VitalMeasurement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pengukuran vital berulang.
 *
 * `medical_record_vitals` tetap diperbarui sebagai snapshot terbaru agar form
 * dan API lama tidak berubah. Riwayat lengkap selalu bertambah di
 * `vital_measurements` dan tidak dapat diedit setelah tercatat.
 */
class VitalMeasurementService
{
    private const VITAL_FIELDS = [
        'temperature_c', 'heart_rate', 'respiratory_rate', 'crt_seconds',
        'mucosa', 'hydration', 'bcs', 'pain_score', 'weight_kg', 'notes',
    ];

    public function recordForRecord(MedicalRecord $record, array $data, ?User $actor = null): VitalMeasurement
    {
        $this->assertClinic($record->clinic_id, $actor);

        return DB::transaction(function () use ($record, $data, $actor) {
            $record = MedicalRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $payload = $this->normalize($data, $record->clinic_id, $record->encounter_id, $record->pet_id, $actor);
            $payload['medical_record_id'] = $record->id;

            $latest = VitalMeasurement::where('medical_record_id', $record->id)
                ->recorded()
                ->latest('measured_at')
                ->latest('id')
                ->first();

            if ($latest && $this->sameVitals($latest, $payload)) {
                $this->updateLegacySnapshot($record, $latest);

                return $latest;
            }

            $measurement = VitalMeasurement::create($payload);
            $this->updateLegacySnapshot($record, $measurement);

            return $measurement;
        });
    }

    public function recordForEncounter(ClinicalEncounter $encounter, array $data, ?User $actor = null): VitalMeasurement
    {
        $this->assertClinic($encounter->clinic_id, $actor);

        return DB::transaction(function () use ($encounter, $data, $actor) {
            $encounter = ClinicalEncounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $record = MedicalRecord::where('encounter_id', $encounter->id)->first();

            $payload = $this->normalize(
                $data,
                $encounter->clinic_id,
                $encounter->id,
                $encounter->pet_id,
                $actor
            );

            if ($record) {
                $payload['medical_record_id'] = $record->id;
            }

            $latest = VitalMeasurement::where('encounter_id', $encounter->id)
                ->recorded()
                ->latest('measured_at')
                ->latest('id')
                ->first();

            if ($latest && $this->sameVitals($latest, $payload)) {
                if ($record) {
                    $this->updateLegacySnapshot($record, $latest);
                }

                return $latest;
            }

            $measurement = VitalMeasurement::create($payload);

            if ($record) {
                $this->updateLegacySnapshot($record, $measurement);
            }

            return $measurement;
        });
    }

    public function seriesForRecord(MedicalRecord $record, bool $includeVoided = false)
    {
        return VitalMeasurement::where('medical_record_id', $record->id)
            ->when(! $includeVoided, fn ($q) => $q->recorded())
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();
    }

    public function seriesForEncounter(ClinicalEncounter $encounter, bool $includeVoided = false)
    {
        return VitalMeasurement::where('encounter_id', $encounter->id)
            ->when(! $includeVoided, fn ($q) => $q->recorded())
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();
    }

    public function void(VitalMeasurement $measurement, string $reason, ?User $actor = null): VitalMeasurement
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'VITAL_VOID_REASON_REQUIRED',
                'Alasan pembatalan pengukuran vital wajib diisi.',
                422
            );
        }

        $this->assertClinic($measurement->clinic_id, $actor);

        if ($measurement->status === VitalMeasurement::STATUS_VOID) {
            throw new MedicalRecordLifecycleException(
                'VITAL_ALREADY_VOID',
                'Pengukuran vital ini sudah dibatalkan.',
                409
            );
        }

        $measurement->forceFill([
            'status' => VitalMeasurement::STATUS_VOID,
            'void_reason' => $reason,
            'voided_at' => now(),
            'voided_by' => $actor?->id,
        ])->save();

        return $measurement->refresh();
    }

    public function resolveShift(?string $shift, ?Carbon $at = null): string
    {
        if ($shift && in_array($shift, VitalMeasurement::SHIFTS, true)) {
            return $shift;
        }

        $hour = ($at ?: now())->hour;

        if ($hour >= 6 && $hour < 14) {
            return VitalMeasurement::SHIFT_DAY;
        }

        return $hour >= 14 && $hour < 22
            ? VitalMeasurement::SHIFT_EVENING
            : VitalMeasurement::SHIFT_NIGHT;
    }

    private function normalize(
        array $data,
        int $clinicId,
        ?int $encounterId,
        int $petId,
        ?User $actor
    ): array {
        $measuredAt = ! empty($data['measured_at'])
            ? Carbon::parse($data['measured_at'])
            : now();

        return [
            'clinic_id' => $clinicId,
            'encounter_id' => $encounterId,
            'medical_record_id' => null,
            'pet_id' => $petId,
            'legacy_vital_id' => null,
            'temperature_c' => $this->number($data, 'temperature_c'),
            'heart_rate' => $this->integer($data, 'heart_rate'),
            'respiratory_rate' => $this->integer($data, 'respiratory_rate'),
            'crt_seconds' => $this->number($data, 'crt_seconds'),
            'mucosa' => $this->text($data, 'mucosa', 50),
            'hydration' => $this->text($data, 'hydration', 30),
            'bcs' => $this->integer($data, 'bcs'),
            'pain_score' => $this->integer($data, 'pain_score'),
            'weight_kg' => $this->number($data, 'weight_kg'),
            'notes' => $this->text($data, 'notes', 500),
            'measured_at' => $measuredAt,
            'source' => in_array(($data['source'] ?? ''), [
                VitalMeasurement::SOURCE_MANUAL,
                VitalMeasurement::SOURCE_LEGACY,
                VitalMeasurement::SOURCE_DEVICE,
            ], true) ? $data['source'] : VitalMeasurement::SOURCE_MANUAL,
            'shift' => $this->resolveShift($data['shift'] ?? null, $measuredAt),
            'status' => VitalMeasurement::STATUS_RECORDED,
            'recorded_by' => $actor?->id,
        ];
    }

    private function updateLegacySnapshot(MedicalRecord $record, VitalMeasurement $measurement): void
    {
        $record->vitals()->updateOrCreate(
            ['medical_record_id' => $record->id],
            [
                'temperature_c' => $measurement->temperature_c,
                'heart_rate' => $measurement->heart_rate,
                'respiratory_rate' => $measurement->respiratory_rate,
                'crt_seconds' => $measurement->crt_seconds,
                'mucosa' => $measurement->mucosa,
                'hydration' => $measurement->hydration,
                'bcs' => $measurement->bcs,
                'pain_score' => $measurement->pain_score,
                'weight_kg' => $measurement->weight_kg,
                'notes' => $measurement->notes,
                'measured_at' => $measurement->measured_at,
            ]
        );
    }

    private function sameVitals(VitalMeasurement $measurement, array $payload): bool
    {
        foreach (self::VITAL_FIELDS as $field) {
            $left = $measurement->getAttribute($field);
            $right = $payload[$field] ?? null;

            if ($left === null && $right === null) {
                continue;
            }

            if (is_numeric($left) && is_numeric($right)) {
                if (abs((float) $left - (float) $right) > 0.0001) {
                    return false;
                }

                continue;
            }

            if ((string) $left !== (string) $right) {
                return false;
            }
        }

        return true;
    }

    private function assertClinic(int $clinicId, ?User $actor): void
    {
        if ($actor && $actor->clinic_id && (int) $actor->clinic_id !== (int) $clinicId) {
            throw new MedicalRecordLifecycleException(
                'VITAL_CLINIC_MISMATCH',
                'Pengukuran vital hanya boleh dicatat pada klinik yang sama.',
                404
            );
        }
    }

    private function number(array $data, string $key): ?float
    {
        return isset($data[$key]) && $data[$key] !== '' ? (float) $data[$key] : null;
    }

    private function integer(array $data, string $key): ?int
    {
        return isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;
    }

    private function text(array $data, string $key, int $max): ?string
    {
        $value = isset($data[$key]) ? trim((string) $data[$key]) : '';

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
