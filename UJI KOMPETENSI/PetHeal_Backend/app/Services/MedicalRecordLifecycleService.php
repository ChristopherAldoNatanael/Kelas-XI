<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu jalur finalisasi dan transisi status.
 *
 * Setelah `medical_records.lifecycle_enforcement_enabled` aktif, admin tidak
 * dapat memfinalkan rekam medis. Finalisasi tetap menghasilkan versi immutable
 * dan hash kanonik, bukan sekadar mengubah kolom status.
 */
class MedicalRecordLifecycleService
{
    public function __construct(
        private readonly MedicalRecordVersionService $versions,
        private readonly ProfessionalCredentialService $credentials
    ) {
    }

    public function finalize(MedicalRecord $record, User $actor): MedicalRecordVersion
    {
        return DB::transaction(function () use ($record, $actor) {
            $locked = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                MedicalRecord::STATUS_VOID,
                MedicalRecord::STATUS_ARCHIVED,
            ], true)) {
                throw new MedicalRecordLifecycleException(
                    'RECORD_NOT_FINALIZABLE',
                    'Rekam medis yang sudah dibatalkan atau diarsipkan tidak dapat difinalkan.',
                    409
                );
            }

            if (config('medical_records.lifecycle_enforcement_enabled', false)
                && $actor->role !== 'doctor') {
                throw new MedicalRecordLifecycleException(
                    'ADMIN_CANNOT_FINALIZE',
                    'Hanya dokter hewan berwenang yang dapat memfinalkan rekam medis.',
                    403
                );
            }

            if (config('medical_records.credential_gate_enabled', false)) {
                $this->credentials->assertCanFinalize($locked);
            }

            $locked->forceFill([
                'status' => MedicalRecord::STATUS_FINAL,
                'finalized_at' => $locked->finalized_at ?: now(),
                'finalized_by' => $locked->finalized_by ?: $actor->id,
            ])->save();

            $version = $this->resolveVersionForContent($locked, (int) $actor->id);

            $this->credentials->captureSnapshots($locked, (int) $version->id, $actor);

            $locked->forceFill([
                'current_version_id' => $version->id,
                'version' => $version->version_no,
                'content_hash' => $version->content_hash,
            ])->save();

            $record->setRawAttributes($locked->getAttributes(), true);
            $record->setRelation('currentVersion', $version);

            return $version;
        });
    }

    public function markInReview(MedicalRecord $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor) {
            $locked = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === MedicalRecord::STATUS_FINAL) {
                throw new MedicalRecordLifecycleException(
                    'RECORD_ALREADY_FINAL',
                    'Rekam medis sudah final dan tidak dapat dikembalikan ke in_review.',
                    409
                );
            }

            $locked->forceFill([
                'status' => MedicalRecord::STATUS_IN_REVIEW,
                'in_review_at' => now(),
                'in_review_by' => $actor->id,
            ])->save();

            $record->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Kembalikan draf/in_review ke draft. Final tidak boleh diturunkan statusnya.
     */
    public function returnToDraft(MedicalRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $locked = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === MedicalRecord::STATUS_FINAL) {
                throw new MedicalRecordLifecycleException(
                    'RECORD_ALREADY_FINAL',
                    'Rekam medis final tidak dapat dikembalikan menjadi draft; gunakan amendment.',
                    409
                );
            }

            $locked->forceFill([
                'status' => MedicalRecord::STATUS_DRAFT,
                'in_review_at' => null,
                'in_review_by' => null,
            ])->save();

            $record->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function resolveVersionForContent(MedicalRecord $record, int $userId): MedicalRecordVersion
    {
        $snapshot = $this->versions->buildSnapshot($record);
        $hash = $this->versions->hashSnapshot($snapshot);

        $current = $record->current_version_id
            ? MedicalRecordVersion::find($record->current_version_id)
            : null;

        if ($current && hash_equals($current->content_hash, $hash)) {
            return $current;
        }

        return $this->versions->createVersion($record, $userId, null, $record->content_hash);
    }
}
