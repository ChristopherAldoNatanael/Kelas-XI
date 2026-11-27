<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RetentionService
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function retentionUntil(MedicalRecord $record): Carbon
    {
        $base = $record->finalized_at ?: $record->created_at ?: now();
        $clinicalYears = max(1, (int) config('medical_records.retention_years', 5));
        $financialYears = max($clinicalYears, (int) config('medical_records.financial_retention_years', 10));

        $settled = in_array($record->extra_payment_status, ['not_required', 'paid'], true);

        return Carbon::parse($base)->addYears($settled ? $clinicalYears : $financialYears)->startOfDay();
    }

    public function applyRetention(MedicalRecord $record): MedicalRecord
    {
        $record->forceFill([
            'retention_until' => $this->retentionUntil($record)->toDateString(),
        ])->save();

        return $record->refresh();
    }

    public function canArchive(MedicalRecord $record): bool
    {
        return $record->status === MedicalRecord::STATUS_FINAL
            && ! $record->legal_hold
            && $record->retention_until !== null
            && $record->retention_until->lte(now()->startOfDay());
    }

    public function archive(MedicalRecord $record, ?User $actor = null): MedicalRecord
    {
        if ($record->legal_hold) {
            throw new MedicalRecordLifecycleException(
                'RETENTION_LEGAL_HOLD',
                'Rekam medis dalam legal hold tidak boleh diarsipkan.',
                422
            );
        }

        if ($record->status !== MedicalRecord::STATUS_FINAL) {
            throw new MedicalRecordLifecycleException(
                'RETENTION_STATUS_INVALID',
                'Hanya rekam medis final yang dapat diarsipkan.',
                422
            );
        }

        if ($record->retention_until === null) {
            $this->applyRetention($record);
            $record->refresh();
        }

        if ($record->retention_until->isFuture()) {
            throw new MedicalRecordLifecycleException(
                'RETENTION_NOT_EXPIRED',
                'Masa retensi rekam medis belum berakhir.',
                422
            );
        }

        return DB::transaction(function () use ($record, $actor) {
            $record->forceFill([
                'status' => MedicalRecord::STATUS_ARCHIVED,
                'archived_at' => now(),
            ])->save();

            $this->audit->record(
                'medical_record.archived',
                $record,
                $actor,
                'allowed',
                null,
                ['record_id' => $record->id],
                $record->clinic_id
            );

            return $record->refresh();
        });
    }
}
