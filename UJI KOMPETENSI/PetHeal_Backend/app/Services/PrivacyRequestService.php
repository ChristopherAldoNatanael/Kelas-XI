<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

class PrivacyRequestService
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function create(User $subject, array $data, User $actor): PrivacyRequest
    {
        if ((int) $actor->id !== (int) $subject->id && ! $actor->isAdmin()) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_REQUEST_NOT_AUTHORIZED',
                'Permintaan hanya boleh dibuat oleh pemilik data atau admin.',
                403
            );
        }

        $type = (string) ($data['request_type'] ?? '');

        if (! in_array($type, PrivacyRequest::TYPES, true)) {
            throw new MedicalRecordLifecycleException('PRIVACY_TYPE_INVALID', 'Jenis permintaan PDP tidak valid.', 422);
        }

        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            throw new MedicalRecordLifecycleException('PRIVACY_DESCRIPTION_REQUIRED', 'Uraian permintaan wajib diisi.', 422);
        }

        $requestedAt = ! empty($data['requested_at'])
            ? Carbon::parse($data['requested_at'])
            : now();

        if ($requestedAt->isFuture()) {
            throw new MedicalRecordLifecycleException('PRIVACY_REQUESTED_IN_FUTURE', 'Waktu permintaan tidak boleh di masa depan.', 422);
        }

        $days = max(1, (int) config('medical_records.privacy_request_due_days', 14));
        $clinicId = $data['clinic_id'] ?? $actor->clinic_id;

        if ($clinicId) {
            $clinicId = (int) $clinicId;
        }

        $request = PrivacyRequest::create([
            'user_id' => $subject->id,
            'clinic_id' => $clinicId,
            'request_type' => $type,
            'status' => PrivacyRequest::STATUS_RECEIVED,
            'description' => mb_substr($description, 0, 20000),
            'requested_at' => $requestedAt,
            'due_at' => $requestedAt->copy()->addDays($days),
        ])->refresh();

        $this->audit->record(
            'privacy_request.created',
            $request,
            $actor,
            'allowed',
            null,
            ['request_type' => $request->request_type, 'status' => $request->status],
            $request->clinic_id
        );

        return $request;
    }

    public function verify(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertCanHandle($request, $actor);

        if ($request->status !== PrivacyRequest::STATUS_RECEIVED) {
            throw new MedicalRecordLifecycleException('PRIVACY_STATUS_INVALID', 'Hanya permintaan received yang dapat diverifikasi.', 422);
        }

        $request->forceFill([
            'status' => PrivacyRequest::STATUS_VERIFIED,
            'verified_at' => now(),
            'handled_by' => $actor->id,
        ])->save();

        return $this->audited($request, $actor, 'privacy_request.verified');
    }

    public function start(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertCanHandle($request, $actor);

        if ($request->status !== PrivacyRequest::STATUS_VERIFIED) {
            throw new MedicalRecordLifecycleException('PRIVACY_STATUS_INVALID', 'Permintaan harus diverifikasi sebelum diproses.', 422);
        }

        $request->forceFill([
            'status' => PrivacyRequest::STATUS_IN_PROGRESS,
            'handled_by' => $actor->id,
        ])->save();

        return $this->audited($request, $actor, 'privacy_request.in_progress');
    }

    public function complete(PrivacyRequest $request, array $data, User $actor): PrivacyRequest
    {
        $this->assertCanHandle($request, $actor);

        if (! in_array($request->status, [PrivacyRequest::STATUS_VERIFIED, PrivacyRequest::STATUS_IN_PROGRESS], true)) {
            throw new MedicalRecordLifecycleException('PRIVACY_STATUS_INVALID', 'Permintaan belum siap diselesaikan.', 422);
        }

        $responsePath = trim((string) ($data['response_path'] ?? ''));

        $request->forceFill([
            'status' => PrivacyRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'handled_by' => $actor->id,
            'response_path' => $responsePath === '' ? null : mb_substr($responsePath, 0, 255),
        ])->save();

        return $this->audited($request, $actor, 'privacy_request.completed');
    }

    public function reject(PrivacyRequest $request, string $reason, User $actor): PrivacyRequest
    {
        $this->assertCanHandle($request, $actor);

        if (in_array($request->status, [PrivacyRequest::STATUS_COMPLETED, PrivacyRequest::STATUS_REJECTED], true)) {
            throw new MedicalRecordLifecycleException('PRIVACY_STATUS_INVALID', 'Permintaan sudah selesai.', 422);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException('PRIVACY_REJECTION_REASON_REQUIRED', 'Alasan penolakan wajib diisi.', 422);
        }

        $request->forceFill([
            'status' => PrivacyRequest::STATUS_REJECTED,
            'rejection_reason' => mb_substr($reason, 0, 20000),
            'completed_at' => now(),
            'handled_by' => $actor->id,
        ])->save();

        return $this->audited($request, $actor, 'privacy_request.rejected');
    }

    /**
     * Alasan permintaan hapus tidak dapat langsung dipenuhi.
     */
    public function erasureBlockReason(User $subject): ?string
    {
        $exists = MedicalRecord::whereHas('pet', fn ($q) => $q->where('user_id', $subject->id))
            ->where(function ($q) {
                $q->where('legal_hold', true)
                    ->orWhere(function ($inner) {
                        $inner->whereIn('status', [MedicalRecord::STATUS_FINAL, MedicalRecord::STATUS_ARCHIVED])
                            ->where(function ($retention) {
                                $retention->whereNull('retention_until')
                                    ->orWhere('retention_until', '>=', now()->startOfDay()->toDateString());
                            });
                    });
            })
            ->exists();

        if (! $exists) {
            return null;
        }

        return 'Rekam medis masih dalam retensi atau legal hold; permintaan hapus harus ditolak dengan alasan hukum.';
    }

    private function audited(PrivacyRequest $request, User $actor, string $action): PrivacyRequest
    {
        $request = $request->refresh();

        $this->audit->record(
            $action,
            $request,
            $actor,
            'allowed',
            null,
            ['request_type' => $request->request_type, 'status' => $request->status],
            $request->clinic_id
        );

        return $request;
    }

    private function assertCanHandle(PrivacyRequest $request, User $actor): void
    {
        if (! $actor->isAdmin()) {
            throw new MedicalRecordLifecycleException('PRIVACY_HANDLER_NOT_AUTHORIZED', 'Hanya admin yang boleh menangani permintaan PDP.', 403);
        }

        if ($actor->clinic_id && $request->clinic_id && (int) $actor->clinic_id !== (int) $request->clinic_id) {
            throw new MedicalRecordLifecycleException('PRIVACY_CLINIC_MISMATCH', 'Permintaan hanya boleh diakses pada klinik yang sama.', 404);
        }
    }
}
