<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LegalHoldService
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function set(MedicalRecord $record, string $reason, User $actor): MedicalRecord
    {
        $this->assertCanManage($record, $actor);

        if ($record->legal_hold) {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_ALREADY_SET', 'Legal hold sudah aktif.', 422);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_REASON_REQUIRED', 'Alasan legal hold wajib diisi.', 422);
        }

        $record->forceFill([
            'legal_hold' => true,
            'legal_hold_reason' => mb_substr($reason, 0, 20000),
            'legal_hold_set_at' => now(),
            'legal_hold_set_by' => $actor->id,
            'legal_hold_release_requested_at' => null,
            'legal_hold_release_requested_by' => null,
            'legal_hold_release_reason' => null,
            'legal_hold_release_approved_at' => null,
            'legal_hold_release_approved_by' => null,
        ])->save();

        $record = $record->refresh();

        $this->audit->record(
            'medical_record.legal_hold.set',
            $record,
            $actor,
            'allowed',
            null,
            ['record_id' => $record->id, 'legal_hold' => true],
            $record->clinic_id
        );

        return $record;
    }

    public function requestRelease(MedicalRecord $record, string $reason, User $actor): MedicalRecord
    {
        $this->assertCanManage($record, $actor);

        if (! $record->legal_hold) {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_NOT_ACTIVE', 'Legal hold tidak aktif.', 422);
        }

        if ($record->legal_hold_release_requested_at) {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_RELEASE_ALREADY_REQUESTED', 'Permohonan pelepasan sudah ada.', 422);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_RELEASE_REASON_REQUIRED', 'Alasan pelepasan legal hold wajib diisi.', 422);
        }

        $record->forceFill([
            'legal_hold_release_requested_at' => now(),
            'legal_hold_release_requested_by' => $actor->id,
            'legal_hold_release_reason' => mb_substr($reason, 0, 20000),
        ])->save();

        $record = $record->refresh();

        $this->audit->record(
            'medical_record.legal_hold.release_requested',
            $record,
            $actor,
            'allowed',
            null,
            ['record_id' => $record->id, 'legal_hold' => true],
            $record->clinic_id
        );

        return $record;
    }

    public function approveRelease(MedicalRecord $record, User $actor): MedicalRecord
    {
        $this->assertCanManage($record, $actor);

        if (! $record->legal_hold) {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_NOT_ACTIVE', 'Legal hold tidak aktif.', 422);
        }

        if (! $record->legal_hold_release_requested_at || ! $record->legal_hold_release_requested_by) {
            throw new MedicalRecordLifecycleException('LEGAL_HOLD_RELEASE_NOT_REQUESTED', 'Permohonan pelepasan belum dibuat.', 422);
        }

        if ((int) $record->legal_hold_release_requested_by === (int) $actor->id) {
            throw new MedicalRecordLifecycleException(
                'LEGAL_HOLD_RELEASE_DUAL_APPROVAL_REQUIRED',
                'Pelepasan legal hold wajib disetujui petugas kedua.',
                403
            );
        }

        return DB::transaction(function () use ($record, $actor) {
            $record->forceFill([
                'legal_hold' => false,
                'legal_hold_release_approved_at' => now(),
                'legal_hold_release_approved_by' => $actor->id,
            ])->save();

            $record = $record->refresh();

            $this->audit->record(
                'medical_record.legal_hold.release_approved',
                $record,
                $actor,
                'allowed',
                null,
                ['record_id' => $record->id, 'legal_hold' => false],
                $record->clinic_id
            );

            return $record;
        });
    }

    private function assertCanManage(MedicalRecord $record, User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw new MedicalRecordLifecycleException(
                'LEGAL_HOLD_NOT_AUTHORIZED',
                'Hanya admin yang boleh mengelola legal hold.',
                403
            );
        }

        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $record->clinic_id) {
            throw new MedicalRecordLifecycleException(
                'LEGAL_HOLD_CLINIC_MISMATCH',
                'Legal hold hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
