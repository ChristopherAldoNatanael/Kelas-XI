<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Amendment klinis/administratif dengan satu antrean aktif per rekam medis.
 *
 * `amended` bukan status rekam medis. Status amendment hanya:
 * requested, approved, rejected, applied, cancelled.
 */
class MedicalRecordAmendmentService
{
    public function __construct(
        private readonly MedicalRecordVersionService $versions,
        private readonly ProfessionalCredentialService $credentials
    ) {
    }

    public function request(
        MedicalRecord $record,
        User $actor,
        string $type,
        string $reason,
        array $after
    ): MedicalRecordAmendment {
        if (! config('medical_records.amendments_enabled', false)) {
            throw new MedicalRecordLifecycleException(
                'AMENDMENTS_DISABLED',
                'Fitur amendment belum diaktifkan untuk klinik ini.',
                403
            );
        }

        if (! in_array($type, [
            MedicalRecordAmendment::TYPE_CLINICAL,
            MedicalRecordAmendment::TYPE_ADMINISTRATIVE,
        ], true)) {
            throw new MedicalRecordLifecycleException('INVALID_AMENDMENT_TYPE', 'Tipe amendment tidak valid.', 422);
        }

        if (trim($reason) === '') {
            throw new MedicalRecordLifecycleException('AMENDMENT_REASON_REQUIRED', 'Alasan amendment wajib diisi.', 422);
        }

        return DB::transaction(function () use ($record, $actor, $type, $reason, $after) {
            $locked = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== MedicalRecord::STATUS_FINAL) {
                throw new MedicalRecordLifecycleException(
                    'RECORD_NOT_FINAL',
                    'Amendment hanya dapat diajukan untuk rekam medis final.',
                    409
                );
            }

            $base = $locked->current_version_id
                ? $locked->currentVersion()->first()
                : null;

            if (! $base) {
                throw new MedicalRecordLifecycleException(
                    'RECORD_NOT_FINAL',
                    'Rekam medis belum memiliki versi final yang dapat dikoreksi.',
                    409
                );
            }

            $pending = MedicalRecordAmendment::where('medical_record_id', $locked->id)
                ->whereIn('status', [
                    MedicalRecordAmendment::STATUS_REQUESTED,
                    MedicalRecordAmendment::STATUS_APPROVED,
                ])
                ->lockForUpdate()
                ->exists();

            if ($pending) {
                throw new MedicalRecordLifecycleException(
                    'AMENDMENT_ALREADY_PENDING',
                    'Sudah ada amendment aktif untuk rekam medis ini.',
                    409
                );
            }

            return MedicalRecordAmendment::create([
                'clinic_id' => $locked->clinic_id,
                'medical_record_id' => $locked->id,
                'base_version_id' => $base->id,
                'amendment_type' => $type,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'reason' => $reason,
                'before' => $base->snapshot,
                'after' => $after,
                'status' => MedicalRecordAmendment::STATUS_REQUESTED,
                'pending_key' => (string) $locked->id,
            ]);
        });
    }

    public function approve(
        MedicalRecordAmendment $amendment,
        User $reviewer,
        ?string $notes = null
    ): MedicalRecordAmendment {
        return DB::transaction(function () use ($amendment, $reviewer, $notes) {
            $locked = MedicalRecordAmendment::whereKey($amendment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== MedicalRecordAmendment::STATUS_REQUESTED) {
                throw new MedicalRecordLifecycleException(
                    'AMENDMENT_NOT_PENDING',
                    'Amendment ini tidak sedang menunggu persetujuan.',
                    409
                );
            }

            if ($locked->amendment_type === MedicalRecordAmendment::TYPE_CLINICAL
                && $reviewer->role !== 'doctor') {
                throw new MedicalRecordLifecycleException(
                    'CLINICAL_REVIEWER_REQUIRED',
                    'Amendment klinis hanya dapat disetujui dokter hewan berwenang.',
                    403
                );
            }

            if ((int) $locked->requested_by === (int) $reviewer->id) {
                throw new MedicalRecordLifecycleException(
                    'REQUESTER_CANNOT_APPROVE',
                    'Pengaju amendment tidak boleh menjadi satu-satunya penyetuju.',
                    403
                );
            }

            $locked->forceFill([
                'status' => MedicalRecordAmendment::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'reviewer_credential_snapshot' => $locked->amendment_type === MedicalRecordAmendment::TYPE_CLINICAL
                    ? $this->credentials->reviewerSnapshot($reviewer)
                    : null,
            ])->save();

            return $locked;
        });
    }

    public function apply(MedicalRecordAmendment $amendment, User $actor): MedicalRecordAmendment
    {
        return DB::transaction(function () use ($amendment, $actor) {
            $lockedAmendment = MedicalRecordAmendment::whereKey($amendment->getKey())->lockForUpdate()->firstOrFail();
            $record = MedicalRecord::whereKey($lockedAmendment->medical_record_id)->lockForUpdate()->firstOrFail();

            if ($lockedAmendment->status !== MedicalRecordAmendment::STATUS_APPROVED) {
                throw new MedicalRecordLifecycleException(
                    'AMENDMENT_NOT_APPROVED',
                    'Amendment harus disetujui sebelum diterapkan.',
                    409
                );
            }

            if ((int) $lockedAmendment->base_version_id !== (int) $record->current_version_id) {
                throw new MedicalRecordLifecycleException(
                    'STALE_VERSION',
                    'Versi dasar amendment sudah berubah. Ajukan amendment baru dari versi terbaru.',
                    409
                );
            }

            if ($record->status !== MedicalRecord::STATUS_FINAL) {
                throw new MedicalRecordLifecycleException(
                    'RECORD_NOT_FINAL',
                    'Amendment hanya dapat diterapkan pada rekam medis final.',
                    409
                );
            }

            $projection = $this->projectionFromSnapshot($lockedAmendment->after);

            if ($lockedAmendment->amendment_type === MedicalRecordAmendment::TYPE_ADMINISTRATIVE) {
                $projection = array_intersect_key($projection, array_flip([
                    'template_key', 'template_version',
                ]));
            } else {
                $projection = array_intersect_key($projection, array_flip($this->clinicalProjectionFields()));
            }

            if ($projection !== []) {
                $record->fill($projection);
                $record->save();
            }

            $version = $this->versions->createVersion(
                $record,
                (int) $actor->id,
                (int) $lockedAmendment->id
            );

            $this->credentials->captureSnapshots($record, (int) $version->id, $actor);

            $record->forceFill([
                'current_version_id' => $version->id,
                'version' => $version->version_no,
                'content_hash' => $version->content_hash,
                'last_amended_at' => now(),
                'status' => MedicalRecord::STATUS_FINAL,
            ])->save();

            $lockedAmendment->forceFill([
                'status' => MedicalRecordAmendment::STATUS_APPLIED,
                'applied_version_id' => $version->id,
                'applied_at' => now(),
                'pending_key' => null,
            ])->save();

            return $lockedAmendment;
        });
    }

    public function reject(MedicalRecordAmendment $amendment, User $reviewer, string $notes): MedicalRecordAmendment
    {
        return DB::transaction(function () use ($amendment, $reviewer, $notes) {
            $locked = MedicalRecordAmendment::whereKey($amendment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== MedicalRecordAmendment::STATUS_REQUESTED) {
                throw new MedicalRecordLifecycleException(
                    'AMENDMENT_NOT_PENDING',
                    'Amendment ini tidak sedang menunggu persetujuan.',
                    409
                );
            }

            if ($locked->amendment_type === MedicalRecordAmendment::TYPE_CLINICAL
                && $reviewer->role !== 'doctor') {
                throw new MedicalRecordLifecycleException(
                    'CLINICAL_REVIEWER_REQUIRED',
                    'Amendment klinis hanya dapat ditinjau dokter hewan berwenang.',
                    403
                );
            }

            $locked->forceFill([
                'status' => MedicalRecordAmendment::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'pending_key' => null,
            ])->save();

            return $locked;
        });
    }

    public function cancel(MedicalRecordAmendment $amendment, User $actor): MedicalRecordAmendment
    {
        return DB::transaction(function () use ($amendment, $actor) {
            $locked = MedicalRecordAmendment::whereKey($amendment->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [
                MedicalRecordAmendment::STATUS_REQUESTED,
                MedicalRecordAmendment::STATUS_APPROVED,
            ], true)) {
                throw new MedicalRecordLifecycleException(
                    'AMENDMENT_NOT_PENDING',
                    'Amendment ini tidak dapat dibatalkan.',
                    409
                );
            }

            if ((int) $locked->requested_by !== (int) $actor->id && $actor->role !== 'super_admin') {
                throw new MedicalRecordLifecycleException(
                    'AMENDMENT_CANCEL_FORBIDDEN',
                    'Hanya pengaju atau super admin yang dapat membatalkan amendment.',
                    403
                );
            }

            $locked->forceFill([
                'status' => MedicalRecordAmendment::STATUS_CANCELLED,
                'pending_key' => null,
            ])->save();

            return $locked;
        });
    }

    private function clinicalProjectionFields(): array
    {
        return [
            'subjective', 'objective', 'assessment', 'plan',
            'diagnosis', 'treatment', 'medicine', 'notes',
            'next_visit_date', 'next_visit_time',
            'cost', 'treatment_cost', 'medicine_cost',
            'outcome', 'cause_of_death', 'euthanasia_reason', 'disposal_method',
        ];
    }

    private function projectionFromSnapshot(array $snapshot): array
    {
        $projection = [];
        $soap = is_array($snapshot['soap'] ?? null) ? $snapshot['soap'] : [];

        foreach (['subjective', 'objective', 'assessment', 'plan'] as $field) {
            if (array_key_exists($field, $soap)) {
                $projection[$field] = $soap[$field];
            } elseif (array_key_exists($field, $snapshot)) {
                $projection[$field] = $snapshot[$field];
            }
        }

        foreach ([
            'diagnosis', 'treatment', 'medicine', 'notes',
            'next_visit_date', 'next_visit_time',
            'cost', 'treatment_cost', 'medicine_cost',
            'outcome', 'cause_of_death', 'euthanasia_reason', 'disposal_method',
            'template_key', 'template_version',
        ] as $field) {
            if (array_key_exists($field, $snapshot)) {
                $projection[$field] = $snapshot[$field];
            }
        }

        return $projection;
    }
}
