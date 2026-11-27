<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Catatan klinis bertanda tangan.
 *
 * Catatan `signed` bersifat immutable. Perubahan isi wajib lewat catatan
 * koreksi baru (`correction_of_id`), sedangkan pembatalan memakai `void`
 * dengan alasan. Hash isi dihitung saat tanda tangan.
 */
class ClinicalNoteService
{
    private const TEXT_FIELDS = ['subjective', 'objective', 'assessment', 'plan', 'body'];

    public function create(ClinicalEncounter $encounter, array $data, User $actor): ClinicalNote
    {
        $this->assertClinic($encounter->clinic_id, $actor);

        $note = ClinicalNote::create($this->payload($encounter, $data, $actor));

        return $note->refresh();
    }

    public function monitoring(
        ClinicalEncounter $encounter,
        array $data,
        User $actor
    ): ClinicalNote {
        $data['note_type'] = ClinicalNote::TYPE_MONITORING;
        $data['shift'] = $data['shift'] ?? $this->resolveShift();

        return $this->create($encounter, $data, $actor);
    }

    public function update(ClinicalNote $note, array $data, User $actor): ClinicalNote
    {
        $this->assertClinic($note->clinic_id, $actor);

        if (! $note->isDraft()) {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_NOT_DRAFT',
                'Catatan yang sudah ditandatangani tidak dapat diedit; buat koreksi baru.',
                409
            );
        }

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $note->{$field} = $this->text($data[$field]);
            }
        }

        if (array_key_exists('note_type', $data)
            && in_array($data['note_type'], ClinicalNote::TYPES, true)) {
            $note->note_type = $data['note_type'];
        }

        if (array_key_exists('shift', $data)) {
            $note->shift = $this->shift($data['shift']);
        }

        $note->save();

        return $note->refresh();
    }

    public function sign(ClinicalNote $note, User $actor): ClinicalNote
    {
        $this->assertClinic($note->clinic_id, $actor);

        if ($actor->role !== 'doctor') {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_SIGNER_INVALID',
                'Hanya dokter hewan yang dapat menandatangani catatan klinis.',
                403
            );
        }

        if ($note->status !== ClinicalNote::STATUS_DRAFT) {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_ALREADY_SIGNED',
                'Catatan klinis ini bukan draft atau sudah ditandatangani.',
                409
            );
        }

        $hash = $this->hash($note);

        $note->forceFill([
            'status' => ClinicalNote::STATUS_SIGNED,
            'signed_by' => $actor->id,
            'signed_at' => now(),
            'content_hash' => $hash,
        ])->save();

        return $note->refresh();
    }

    public function correct(ClinicalNote $original, array $data, User $actor): ClinicalNote
    {
        $this->assertClinic($original->clinic_id, $actor);

        if (! $original->isSigned()) {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_NOT_SIGNED',
                'Koreksi hanya dapat dibuat untuk catatan yang sudah ditandatangani.',
                409
            );
        }

        $encounter = $original->encounter;

        $payload = $this->payload($encounter, $data, $actor);
        $payload['note_type'] = ClinicalNote::TYPE_CORRECTION;
        $payload['correction_of_id'] = $original->id;

        return ClinicalNote::create($payload)->refresh();
    }

    public function void(ClinicalNote $note, string $reason, User $actor): ClinicalNote
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_VOID_REASON_REQUIRED',
                'Alasan pembatalan catatan klinis wajib diisi.',
                422
            );
        }

        $this->assertClinic($note->clinic_id, $actor);

        if (! $note->isSigned()) {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_NOT_SIGNED',
                'Hanya catatan bertanda tangan yang dapat di-void.',
                409
            );
        }

        $note->forceFill([
            'status' => ClinicalNote::STATUS_VOID,
            'void_reason' => $reason,
            'voided_at' => now(),
            'voided_by' => $actor->id,
        ])->save();

        return $note->refresh();
    }

    public function hash(ClinicalNote $note): string
    {
        $payload = [
            'clinic_id' => $note->clinic_id,
            'encounter_id' => $note->encounter_id,
            'medical_record_id' => $note->medical_record_id,
            'note_type' => $note->note_type,
            'shift' => $note->shift,
            'subjective' => $note->subjective,
            'objective' => $note->objective,
            'assessment' => $note->assessment,
            'plan' => $note->plan,
            'body' => $note->body,
            'recorded_by' => $note->recorded_by,
            'recorded_at' => optional($note->recorded_at)->toDateTimeString(),
            'correction_of_id' => $note->correction_of_id,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function payload(ClinicalEncounter $encounter, array $data, User $actor): array
    {
        $type = in_array(($data['note_type'] ?? ''), ClinicalNote::TYPES, true)
            ? $data['note_type']
            : ClinicalNote::TYPE_PROGRESS;

        $record = DB::table('medical_records')
            ->where('encounter_id', $encounter->id)
            ->value('id');

        return [
            'clinic_id' => $encounter->clinic_id,
            'encounter_id' => $encounter->id,
            'medical_record_id' => $record,
            'note_type' => $type,
            'shift' => $this->shift($data['shift'] ?? null),
            'status' => ClinicalNote::STATUS_DRAFT,
            'subjective' => $this->text($data['subjective'] ?? null),
            'objective' => $this->text($data['objective'] ?? null),
            'assessment' => $this->text($data['assessment'] ?? null),
            'plan' => $this->text($data['plan'] ?? null),
            'body' => $this->text($data['body'] ?? null),
            'recorded_by' => $actor->id,
            'recorded_at' => ! empty($data['recorded_at'])
                ? \Illuminate\Support\Carbon::parse($data['recorded_at'])
                : now(),
        ];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 20000);
    }

    private function shift(?string $shift): ?string
    {
        return $shift && in_array($shift, ClinicalNote::SHIFTS, true) ? $shift : null;
    }

    private function resolveShift(): string
    {
        $hour = now()->hour;

        if ($hour >= 6 && $hour < 14) {
            return ClinicalNote::SHIFT_DAY;
        }

        return $hour >= 14 && $hour < 22
            ? ClinicalNote::SHIFT_EVENING
            : ClinicalNote::SHIFT_NIGHT;
    }

    private function assertClinic(int $clinicId, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $clinicId) {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_NOTE_CLINIC_MISMATCH',
                'Catatan klinis hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
