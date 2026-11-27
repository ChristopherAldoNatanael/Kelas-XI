<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot immutable dan hash kanonik rekam medis.
 *
 * Kolom proyeksi lama tetap diperbarui sebagai cache kompatibilitas. Sumber
 * kebenaran isi adalah snapshot versi yang ditunjuk `current_version_id`.
 */
class MedicalRecordVersionService
{
    public const SCHEMA_VERSION = '1.5';

    public function createVersion(
        MedicalRecord $record,
        ?int $userId = null,
        ?int $amendmentId = null,
        ?string $legacyHash = null
    ): MedicalRecordVersion {
        return DB::transaction(function () use ($record, $userId, $amendmentId, $legacyHash) {
            $locked = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $versionNo = ((int) MedicalRecordVersion::where('medical_record_id', $locked->id)
                ->max('version_no')) + 1;

            $snapshot = $this->buildSnapshot($locked);
            $hash = $this->hashSnapshot($snapshot);

            $version = MedicalRecordVersion::create([
                'clinic_id' => $locked->clinic_id,
                'medical_record_id' => $locked->id,
                'version_no' => $versionNo,
                'snapshot' => $snapshot,
                'content_hash' => $hash,
                'legacy_content_hash' => $legacyHash ?: $locked->content_hash,
                'finalized_at' => now(),
                'finalized_by' => $userId,
                'amendment_id' => $amendmentId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $locked->forceFill([
                'current_version_id' => $version->id,
                'version' => $versionNo,
                'content_hash' => $hash,
            ])->save();

            $record->setRawAttributes($locked->getAttributes(), true);
            $record->setRelation('currentVersion', $version);

            return $version;
        });
    }

    public function buildSnapshot(MedicalRecord $record): array
    {
        $record->loadMissing([
            'encounter', 'prescriptionItems', 'vitals', 'vitalMeasurements', 'attachments',
            'labResults', 'treatmentConsents', 'welfareAssessments', 'referrals',
            'inpatientStay.locationLogs', 'inpatientStay.medicationAdministrations',
            'notifiableDiseaseReports',
        ]);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'record_id' => $record->id,
            'encounter_id' => $record->encounter_id,
            'clinic_id' => $record->clinic_id,
            'pet_id' => $record->pet_id,
            'doctor_id' => $record->doctor_id,
            'template_key' => $record->template_key,
            'template_version' => $record->template_version,
            'status' => $record->status,
            'soap' => [
                'subjective' => $record->subjective,
                'objective' => $record->objective,
                'assessment' => $record->assessment,
                'plan' => $record->plan,
            ],
            'diagnosis' => $record->diagnosis,
            'treatment' => $record->treatment,
            'medicine' => $record->medicine,
            'notes' => $record->notes,
            'outcome' => $record->outcome,
            'cause_of_death' => $record->cause_of_death,
            'euthanasia_reason' => $record->euthanasia_reason,
            'disposal_method' => $record->disposal_method,
            'next_visit_date' => $record->next_visit_date?->format('Y-m-d'),
            'next_visit_time' => $record->next_visit_time,
            'cost' => (string) $record->cost,
            'treatment_cost' => (string) $record->treatment_cost,
            'medicine_cost' => (string) $record->medicine_cost,
            'prescription' => $record->prescriptionItems->map(fn ($item) => [
                'id' => $item->id,
                'medicine_id' => $item->medicine_id,
                'medicine_name' => $item->medicine_name,
                'dose' => $item->dose,
                'route' => $item->route,
                'frequency' => $item->frequency,
                'duration_days' => $item->duration_days,
                'instruction' => $item->instruction,
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (string) $item->unit_price,
                'batch_number' => $item->batch_number,
                'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                'drug_class' => $item->drug_class,
                'is_antibiotic' => (bool) $item->is_antibiotic,
            ])->values()->all(),
            'vitals' => $record->vitals ? $record->vitals->only([
                'temperature_c', 'heart_rate', 'respiratory_rate', 'crt_seconds',
                'mucosa', 'hydration', 'bcs', 'pain_score', 'weight_kg', 'notes',
                'measured_at',
            ]) : null,
            'vital_series' => $record->vitalMeasurements
                ->where('status', \App\Models\VitalMeasurement::STATUS_RECORDED)
                ->map(fn ($measurement) => $measurement->toSeriesArray())
                ->values()
                ->all(),
            'lab_results' => $record->labResults->map(fn ($result) => [
                'id' => $result->id,
                'lab_order_id' => $result->lab_order_id,
                'lab_order_item_id' => $result->lab_order_item_id,
                'lab_specimen_id' => $result->lab_specimen_id,
                'test_name' => $result->test_name,
                'parameter' => $result->parameter,
                'species' => $result->species,
                'value' => $result->value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'reference_low' => $result->reference_low !== null ? (string) $result->reference_low : null,
                'reference_high' => $result->reference_high !== null ? (string) $result->reference_high : null,
                'reference_status' => $result->reference_status,
                'reference_range_id' => $result->reference_range_id,
                'flag' => $result->flag,
                'status' => $result->status,
                'method' => $result->method,
                'performed_at' => $result->performed_at?->toIso8601String(),
                'resulted_at' => $result->resulted_at?->toIso8601String(),
                'verified_at' => $result->verified_at?->toIso8601String(),
                'verified_by' => $result->verified_by,
                'correction_of_id' => $result->correction_of_id,
                'notes' => $result->notes,
            ])->values()->all(),
            'consents' => $record->treatmentConsents->map(fn ($consent) => [
                'id' => $consent->id,
                'consent_type' => $consent->consent_type,
                'template_key' => $consent->template_key,
                'template_version' => $consent->template_version,
                'consent_text_hash' => $consent->consent_text_hash,
                'signer_name' => $consent->signer_name,
                'signer_relation' => $consent->signer_relation,
                'guardian_name' => $consent->guardian_name,
                'guardian_relation' => $consent->guardian_relation,
                'signed_at' => $consent->signed_at?->toIso8601String(),
                'witness_name' => $consent->witness_name,
                'emergency_basis' => $consent->emergency_basis,
                'evidence_hash' => $consent->evidence_hash,
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
                'revoked_reason' => $consent->revoked_reason,
            ])->values()->all(),
            'welfare_assessments' => $record->welfareAssessments->map(fn ($assessment) => [
                'id' => $assessment->id,
                'freedom_hunger_thirst' => $assessment->freedom_hunger_thirst,
                'freedom_discomfort' => $assessment->freedom_discomfort,
                'freedom_pain_injury_disease' => $assessment->freedom_pain_injury_disease,
                'freedom_fear_distress' => $assessment->freedom_fear_distress,
                'freedom_natural_behavior' => $assessment->freedom_natural_behavior,
                'overall_score' => $assessment->overall_score,
                'notes' => $assessment->notes,
                'assessed_by' => $assessment->assessed_by,
                'assessed_at' => $assessment->assessed_at?->toIso8601String(),
            ])->values()->all(),
            'referrals' => $record->referrals->map(fn ($referral) => [
                'id' => $referral->id,
                'direction' => $referral->direction,
                'referral_number' => $referral->referral_number,
                'referred_to' => $referral->referred_to,
                'external_facility' => $referral->external_facility,
                'reason' => $referral->reason,
                'summary' => $referral->summary,
                'status' => $referral->status,
                'document_hash' => $referral->document_hash,
                'referred_at' => $referral->referred_at?->toIso8601String(),
                'verified_by' => $referral->verified_by,
                'verified_at' => $referral->verified_at?->toIso8601String(),
                'voided_at' => $referral->voided_at?->toIso8601String(),
                'void_reason' => $referral->void_reason,
            ])->values()->all(),
            'notifiable_disease_reports' => $record->notifiableDiseaseReports->map(fn ($report) => [
                'id' => $report->id,
                'disease_code' => $report->disease_code,
                'disease_name' => $report->disease_name,
                'classification' => $report->classification,
                'indicated_at' => $report->indicated_at?->toIso8601String(),
                'report_deadline_at' => $report->report_deadline_at?->toIso8601String(),
                'status' => $report->status,
                'reported_at' => $report->reported_at?->toIso8601String(),
                'reported_by' => $report->reported_by,
                'isikhnas_reference' => $report->isikhnas_reference,
                'evidence_path' => $report->evidence_path,
                'verified_by' => $report->verified_by,
                'verified_at' => $report->verified_at?->toIso8601String(),
                'cancelled_at' => $report->cancelled_at?->toIso8601String(),
                'cancel_reason' => $report->cancel_reason,
                'notes' => $report->notes,
            ])->values()->all(),
            'inpatient_stay' => $record->inpatientStay ? [
                'id' => $record->inpatientStay->id,
                'encounter_id' => $record->inpatientStay->encounter_id,
                'admitted_at' => $record->inpatientStay->admitted_at?->toIso8601String(),
                'discharged_at' => $record->inpatientStay->discharged_at?->toIso8601String(),
                'ward' => $record->inpatientStay->ward,
                'kennel_code' => $record->inpatientStay->kennel_code,
                'status' => $record->inpatientStay->status,
                'admitting_doctor_id' => $record->inpatientStay->admitting_doctor_id,
                'discharge_summary' => $record->inpatientStay->discharge_summary,
                'discharge_instructions' => $record->inpatientStay->discharge_instructions,
                'location_logs' => $record->inpatientStay->locationLogs->map(fn ($log) => [
                    'from_ward' => $log->from_ward,
                    'from_kennel_code' => $log->from_kennel_code,
                    'to_ward' => $log->to_ward,
                    'to_kennel_code' => $log->to_kennel_code,
                    'moved_at' => $log->moved_at?->toIso8601String(),
                    'reason' => $log->reason,
                ])->values()->all(),
                'medication_administrations' => $record->inpatientStay->medicationAdministrations->map(fn ($med) => [
                    'id' => $med->id,
                    'prescription_item_id' => $med->prescription_item_id,
                    'order_source' => $med->order_source,
                    'medicine_id' => $med->medicine_id,
                    'batch_id' => $med->batch_id,
                    'dose_value' => (string) $med->dose_value,
                    'dose_unit' => $med->dose_unit,
                    'route' => $med->route,
                    'administered_at' => $med->administered_at?->toIso8601String(),
                    'administered_by' => $med->administered_by,
                    'status' => $med->status,
                    'notes' => $med->notes,
                ])->values()->all(),
            ] : null,
            'attachments' => $record->attachments->map(fn ($attachment) => [
                'attachment_id' => $attachment->id,
                'sha256' => $attachment->sha256,
                'filename' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => (int) $attachment->size_bytes,
                'finalized_at' => $attachment->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function canonicalJson(array $snapshot): string
    {
        $canonical = $this->canonicalize($snapshot);

        return json_encode(
            $canonical,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    public function hashSnapshot(array $snapshot): string
    {
        return hash('sha256', $this->canonicalJson($snapshot));
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn ($item) => $this->canonicalize($item), $value);
            }

            ksort($value, SORT_STRING);

            foreach ($value as $key => $item) {
                $value[$key] = $this->canonicalize($item);
            }

            return $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        if (is_object($value)) {
            return $this->canonicalize((array) $value);
        }

        return $value;
    }
}
