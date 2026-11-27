<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat perubahan isi rekam medis.
 *
 * Controller memotret kondisi SEBELUM perubahan lewat `snapshot()`, lalu
 * memanggil `record()` setelah data tersimpan. Yang disimpan hanya field yang
 * benar-benar berubah, ditambah salinan resep saat ini — sehingga koreksi
 * resep tetap meninggalkan bukti obat yang pernah diberikan.
 */
class MedicalRecordRevisionService
{
    public function __construct(private readonly AuditChainService $chain)
    {
    }

    /** Field yang dianggap isi dokumen dan wajib punya riwayat. */
    private const TRACKED_FIELDS = [
        'status',
        'template_key',
        'template_version',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'diagnosis',
        'treatment',
        'medicine',
        'notes',
        'next_visit_date',
        'next_visit_time',
        'cost',
        'treatment_cost',
        'medicine_cost',
        'outcome',
        'cause_of_death',
        'euthanasia_reason',
        'disposal_method',
        'void_reason',
    ];

    /**
     * Potret isi rekam medis dalam bentuk yang stabil (string/array), supaya
     * mudah dibandingkan dan disimpan sebagai JSON.
     */
    public function snapshot(MedicalRecord $record): array
    {
        $record->loadMissing(['prescriptionItems', 'vitals', 'vitalMeasurements', 'labResults', 'treatmentConsents', 'welfareAssessments', 'referrals', 'inpatientStay.locationLogs', 'inpatientStay.medicationAdministrations', 'notifiableDiseaseReports']);

        $data = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $value = $record->{$field};

            if ($value instanceof Carbon) {
                $value = $value->toDateTimeString();
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }

            $data[$field] = $value;
        }

        $data['prescription'] = $record->prescriptionItems
            ->map(fn ($item) => [
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
            ])
            ->values()
            ->all();

        $data['vitals'] = $record->vitals
            ? $record->vitals->only([
                'temperature_c', 'heart_rate', 'respiratory_rate', 'crt_seconds',
                'mucosa', 'hydration', 'bcs', 'pain_score', 'weight_kg', 'notes',
            ])
            : null;

        $data['vital_series'] = $record->vitalMeasurements
            ->where('status', \App\Models\VitalMeasurement::STATUS_RECORDED)
            ->map(fn ($measurement) => $measurement->toSeriesArray())
            ->values()
            ->all();

        $data['lab_results'] = $record->labResults
            ->map(fn ($result) => [
                'id' => $result->id,
                'lab_order_id' => $result->lab_order_id,
                'test_name' => $result->test_name,
                'parameter' => $result->parameter,
                'value' => $result->value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'reference_status' => $result->reference_status,
                'flag' => $result->flag,
                'status' => $result->status,
                'verified_by' => $result->verified_by,
                'verified_at' => $result->verified_at?->toDateTimeString(),
                'correction_of_id' => $result->correction_of_id,
            ])
            ->values()
            ->all();

        $data['treatment_consents'] = $record->treatmentConsents
            ->map(fn ($consent) => [
                'id' => $consent->id,
                'consent_type' => $consent->consent_type,
                'template_key' => $consent->template_key,
                'template_version' => $consent->template_version,
                'consent_text_hash' => $consent->consent_text_hash,
                'signer_name' => $consent->signer_name,
                'signed_at' => $consent->signed_at?->toDateTimeString(),
                'emergency_basis' => $consent->emergency_basis,
                'revoked_at' => $consent->revoked_at?->toDateTimeString(),
                'revoked_reason' => $consent->revoked_reason,
            ])
            ->values()
            ->all();

        $data['welfare_assessments'] = $record->welfareAssessments
            ->map(fn ($assessment) => [
                'id' => $assessment->id,
                'overall_score' => $assessment->overall_score,
                'assessed_at' => $assessment->assessed_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $data['referrals'] = $record->referrals
            ->map(fn ($referral) => [
                'id' => $referral->id,
                'direction' => $referral->direction,
                'referral_number' => $referral->referral_number,
                'referred_to' => $referral->referred_to,
                'status' => $referral->status,
                'document_hash' => $referral->document_hash,
                'voided_at' => $referral->voided_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $data['notifiable_disease_reports'] = $record->notifiableDiseaseReports
            ->map(fn ($report) => [
                'id' => $report->id,
                'disease_code' => $report->disease_code,
                'classification' => $report->classification,
                'status' => $report->status,
                'report_deadline_at' => $report->report_deadline_at?->toDateTimeString(),
                'reported_at' => $report->reported_at?->toDateTimeString(),
                'isikhnas_reference' => $report->isikhnas_reference,
                'cancelled_at' => $report->cancelled_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $data['inpatient_stay'] = $record->inpatientStay ? [
            'id' => $record->inpatientStay->id,
            'status' => $record->inpatientStay->status,
            'admitted_at' => $record->inpatientStay->admitted_at?->toDateTimeString(),
            'discharged_at' => $record->inpatientStay->discharged_at?->toDateTimeString(),
            'ward' => $record->inpatientStay->ward,
            'kennel_code' => $record->inpatientStay->kennel_code,
            'medication_administrations' => $record->inpatientStay->medicationAdministrations
                ->map(fn ($med) => [
                    'id' => $med->id,
                    'medicine_id' => $med->medicine_id,
                    'dose_value' => (string) $med->dose_value,
                    'administered_at' => $med->administered_at?->toDateTimeString(),
                    'status' => $med->status,
                ])->values()->all(),
        ] : null;

        return $data;
    }

    /**
     * Simpan satu baris revisi. Mengembalikan null bila tidak ada perubahan
     * (mis. simpan ulang tanpa mengubah apa pun) agar riwayat tidak penuh
     * baris kosong.
     */
    public function record(
        MedicalRecord $record,
        string $action,
        array $before,
        array $after,
        ?string $reason = null,
        ?int $userId = null
    ): ?MedicalRecordRevision {
        $changedBefore = [];
        $changedAfter = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;

            if ($this->same($old, $value)) {
                continue;
            }

            $changedBefore[$key] = $old;
            $changedAfter[$key] = $value;
        }

        if (empty($changedAfter) && $action !== 'create') {
            return null;
        }

        return DB::transaction(function () use ($record, $action, $changedBefore, $changedAfter, $reason, $userId) {
            MedicalRecord::whereKey($record->id)->lockForUpdate()->first();

            $previous = MedicalRecordRevision::where('medical_record_id', $record->id)
                ->orderByDesc('id')
                ->first();

            $revision = new MedicalRecordRevision([
                'medical_record_id' => $record->id,
                'changed_by' => $userId ?: Auth::id(),
                'action' => $action,
                'before' => $changedBefore ?: null,
                'after' => $changedAfter ?: null,
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'created_at' => now(),
            ]);

            $previousHash = $previous?->entry_hash ?: AuditChainService::GENESIS;
            $revision->prev_hash = $previousHash;
            $revision->entry_hash = $this->chain->entryHash(
                $previousHash,
                $this->chain->payload($revision)
            );
            $revision->save();

            return $revision;
        });
    }

    private function same($old, $new): bool
    {
        // Nilai numerik dari database bisa berupa string ("100.00") dan dari
        // form berupa float; bandingkan sebagai string ternormalisasi agar
        // tidak tercatat sebagai perubahan palsu.
        if (is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 0.00001;
        }

        if (is_array($old) || is_array($new)) {
            return $old == $new;
        }

        return (string) $old === (string) $new;
    }
}
