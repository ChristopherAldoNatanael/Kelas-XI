<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\AnimalWelfareAssessment;
use App\Models\ClinicalEncounter;
use App\Models\MedicalRecord;
use App\Models\User;

/**
 * Penilaian kesejahteraan hewan (five freedoms).
 *
 * Setiap penilaian adalah baris baru. Skor wajib 1–5; skor total dihitung
 * server-side agar tidak dapat dimanipulasi dari klien.
 */
class AnimalWelfareService
{
    public function assess(MedicalRecord $record, array $data, User $actor): AnimalWelfareAssessment
    {
        $this->assertClinic($record->clinic_id, $actor);

        $scores = [];

        foreach (AnimalWelfareAssessment::FIELDS as $field) {
            $value = $data[$field] ?? null;

            if (! is_numeric($value) || (int) $value < 1 || (int) $value > 5) {
                throw new MedicalRecordLifecycleException(
                    'WELFARE_SCORE_INVALID',
                    'Setiap skor kesejahteraan wajib berupa angka 1 sampai 5.',
                    422
                );
            }

            $scores[$field] = (int) $value;
        }

        return AnimalWelfareAssessment::create(array_merge($scores, [
            'clinic_id' => $record->clinic_id,
            'medical_record_id' => $record->id,
            'encounter_id' => $record->encounter_id,
            'overall_score' => array_sum($scores),
            'notes' => $this->text($data['notes'] ?? null),
            'assessed_by' => $actor->id,
            'assessed_at' => ! empty($data['assessed_at'])
                ? \Illuminate\Support\Carbon::parse($data['assessed_at'])
                : now(),
        ]))->refresh();
    }

    public function assessEncounter(
        ClinicalEncounter $encounter,
        array $data,
        User $actor
    ): AnimalWelfareAssessment {
        $this->assertClinic($encounter->clinic_id, $actor);

        $record = MedicalRecord::where('encounter_id', $encounter->id)->first();

        if (! $record) {
            throw new MedicalRecordLifecycleException(
                'WELFARE_RECORD_REQUIRED',
                'Rekam medis harus dibuat sebelum penilaian kesejahteraan.',
                409
            );
        }

        return $this->assess($record, $data, $actor);
    }

    public function seriesForRecord(MedicalRecord $record)
    {
        return AnimalWelfareAssessment::where('medical_record_id', $record->id)
            ->orderBy('assessed_at')
            ->orderBy('id')
            ->get();
    }

    public function latestForRecord(MedicalRecord $record): ?AnimalWelfareAssessment
    {
        return AnimalWelfareAssessment::where('medical_record_id', $record->id)
            ->latest('assessed_at')
            ->latest('id')
            ->first();
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
                'WELFARE_CLINIC_MISMATCH',
                'Penilaian kesejahteraan hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
