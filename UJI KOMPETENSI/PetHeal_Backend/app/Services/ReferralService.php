<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    public function create(MedicalRecord $record, array $data, User $actor): Referral
    {
        $this->assertRecordClinic($record, $actor);

        $direction = $data['direction'] ?? Referral::DIRECTION_OUTGOING;

        if (! in_array($direction, [Referral::DIRECTION_OUTGOING, Referral::DIRECTION_INCOMING], true)) {
            throw new MedicalRecordLifecycleException('REFERRAL_DIRECTION_INVALID', 'Arah rujukan tidak valid.', 422);
        }

        $to = trim((string) ($data['referred_to'] ?? ''));

        if ($to === '') {
            throw new MedicalRecordLifecycleException('REFERRAL_TARGET_REQUIRED', 'Tujuan rujukan wajib diisi.', 422);
        }

        $summary = trim((string) ($data['summary'] ?? ''));

        if ($direction === Referral::DIRECTION_OUTGOING && $summary === '') {
            throw new MedicalRecordLifecycleException(
                'REFERRAL_SUMMARY_REQUIRED',
                'Ringkasan klinis wajib diisi untuk rujukan keluar.',
                422
            );
        }

        return DB::transaction(function () use ($record, $data, $actor, $direction, $to, $summary) {
            $number = $direction === Referral::DIRECTION_OUTGOING
                ? $this->number($record->clinic_id)
                : null;

            $payload = [
                'medical_record_id' => $record->id,
                'clinic_id' => $record->clinic_id,
                'direction' => $direction,
                'referral_number' => $number,
                'referred_to' => mb_substr($to, 0, 150),
                'external_facility' => $this->text($data['external_facility'] ?? null, 150),
                'reason' => $this->text($data['reason'] ?? null, 255),
                'notes' => $this->text($data['notes'] ?? null, 20000),
                'summary' => $summary === '' ? null : $summary,
                'status' => Referral::STATUS_SENT,
                'referred_at' => ! empty($data['referred_at'])
                    ? \Illuminate\Support\Carbon::parse($data['referred_at'])
                    : now(),
            ];

            $payload['document_hash'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Referral::create($payload)->refresh();
        });
    }

    public function markAccepted(Referral $referral, User $actor): Referral
    {
        $this->assertRowClinic($referral, $actor);
        $this->assertNotVoided($referral);

        if ($referral->status !== Referral::STATUS_SENT) {
            throw new MedicalRecordLifecycleException('REFERRAL_STATUS_INVALID', 'Hanya rujukan terkirim yang dapat diterima.', 422);
        }

        $referral->forceFill(['status' => Referral::STATUS_ACCEPTED])->save();

        return $referral->refresh();
    }

    public function markReturned(Referral $referral, ?string $resultNotes, User $actor): Referral
    {
        $this->assertRowClinic($referral, $actor);
        $this->assertNotVoided($referral);

        if (! in_array($referral->status, [Referral::STATUS_SENT, Referral::STATUS_ACCEPTED], true)) {
            throw new MedicalRecordLifecycleException('REFERRAL_STATUS_INVALID', 'Rujukan tidak dapat ditandai kembali.', 422);
        }

        $referral->forceFill([
            'status' => Referral::STATUS_RETURNED,
            'returned_at' => now(),
            'result_notes' => $this->text($resultNotes, 20000),
        ])->save();

        return $referral->refresh();
    }

    public function verify(Referral $referral, User $actor): Referral
    {
        $this->assertRowClinic($referral, $actor);
        $this->assertNotVoided($referral);

        if ($actor->role !== 'doctor') {
            throw new MedicalRecordLifecycleException(
                'REFERRAL_VERIFIER_NOT_DOCTOR',
                'Hanya dokter yang boleh memverifikasi rujukan.',
                403
            );
        }

        $referral->forceFill([
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ])->save();

        return $referral->refresh();
    }

    public function void(Referral $referral, string $reason, User $actor): Referral
    {
        $this->assertRowClinic($referral, $actor);
        $this->assertNotVoided($referral);

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'REFERRAL_VOID_REASON_REQUIRED',
                'Alasan pembatalan rujukan wajib diisi.',
                422
            );
        }

        $referral->forceFill([
            'status' => Referral::STATUS_VOID,
            'voided_at' => now(),
            'voided_by' => $actor->id,
            'void_reason' => mb_substr($reason, 0, 20000),
        ])->save();

        return $referral->refresh();
    }

    private function number(int $clinicId): string
    {
        do {
            $candidate = 'RJ-' . $clinicId . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Referral::where('clinic_id', $clinicId)->where('referral_number', $candidate)->exists());

        return $candidate;
    }

    private function assertNotVoided(Referral $referral): void
    {
        if ($referral->isVoided()) {
            throw new MedicalRecordLifecycleException(
                'REFERRAL_VOIDED',
                'Rujukan sudah dibatalkan dan tidak dapat diubah.',
                422
            );
        }
    }

    private function assertRecordClinic(MedicalRecord $record, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $record->clinic_id) {
            throw new MedicalRecordLifecycleException(
                'REFERRAL_CLINIC_MISMATCH',
                'Rujukan hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }

    private function assertRowClinic(Referral $referral, User $actor): void
    {
        $this->assertRecordClinic($referral->record, $actor);
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
