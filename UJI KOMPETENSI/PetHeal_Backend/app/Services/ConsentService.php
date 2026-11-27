<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Models\MedicalRecord;
use App\Models\TreatmentConsent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan tindakan dan persetujuan PDP.
 *
 * Template consent berversi dan tidak diedit di tempat. Consent tindakan
 * menyimpan hash teks yang disetujui. Pencabutan tidak menghapus bukti lama.
 * Tindakan darurat dicatat dengan dasar yang eksplisit, tanpa mengubah
 * kredensial tenaga.
 */
class ConsentService
{
    public function publishTemplate(array $data, User $actor): ConsentTemplate
    {
        $clinicId = (int) $actor->clinic_id;
        $key = trim((string) ($data['template_key'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $type = trim((string) ($data['consent_type'] ?? ''));

        if ($clinicId <= 0) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_TEMPLATE_CLINIC_REQUIRED',
                'Template consent wajib memiliki klinik.',
                422
            );
        }

        if ($key === '' || $body === '' || $name === '') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_TEMPLATE_INCOMPLETE',
                'Kunci, nama, dan isi template consent wajib diisi.',
                422
            );
        }

        if (! in_array($type, TreatmentConsent::TYPES, true)) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_TEMPLATE_TYPE_INVALID',
                'Jenis consent template tidak valid.',
                422
            );
        }

        return DB::transaction(function () use ($clinicId, $key, $body, $name, $type, $data, $actor) {
            $current = ConsentTemplate::where('clinic_id', $clinicId)
                ->where('template_key', $key)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            $version = isset($data['version']) && $data['version'] !== ''
                ? (int) $data['version']
                : (($current?->version ?? 0) + 1);

            if (ConsentTemplate::where('clinic_id', $clinicId)
                ->where('template_key', $key)
                ->where('version', $version)
                ->exists()) {
                throw new MedicalRecordLifecycleException(
                    'CONSENT_TEMPLATE_VERSION_EXISTS',
                    'Versi template consent tersebut sudah ada.',
                    409
                );
            }

            if ($current) {
                $current->forceFill([
                    'is_current' => false,
                    'active_key' => null,
                ])->save();
            }

            return ConsentTemplate::create([
                'clinic_id' => $clinicId,
                'template_key' => mb_substr($key, 0, 80),
                'name' => mb_substr($name, 0, 150),
                'consent_type' => $type,
                'version' => $version,
                'body' => $body,
                'body_hash' => hash('sha256', $body),
                'is_current' => true,
                'active_key' => $clinicId . ':' . $key,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function createTreatmentConsent(MedicalRecord $record, array $data, User $actor): TreatmentConsent
    {
        $this->assertClinic($record->clinic_id, $actor);

        $type = trim((string) ($data['consent_type'] ?? ''));

        if (! in_array($type, TreatmentConsent::TYPES, true) || $type === TreatmentConsent::TYPE_DARURAT) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_TYPE_INVALID',
                'Jenis consent tindakan tidak valid.',
                422
            );
        }

        $signer = trim((string) ($data['signer_name'] ?? ''));

        if ($signer === '') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_SIGNER_REQUIRED',
                'Nama penanda tangan consent wajib diisi.',
                422
            );
        }

        $template = $this->resolveTemplate($record->clinic_id, $data);
        $text = trim((string) ($data['consent_text'] ?? ''));
        $hash = $template?->body_hash ?: ($text !== '' ? hash('sha256', $text) : null);

        return TreatmentConsent::create([
            'clinic_id' => $record->clinic_id,
            'medical_record_id' => $record->id,
            'pet_id' => $record->pet_id,
            'user_id' => $data['user_id'] ?? $record->pet?->user_id,
            'consent_type' => $type,
            'template_key' => $template?->template_key,
            'template_version' => $template?->version,
            'consent_text_hash' => $hash,
            'risks_explained' => $this->text($data['risks_explained'] ?? null),
            'signer_name' => mb_substr($signer, 0, 120),
            'signer_relation' => mb_substr(trim((string) ($data['signer_relation'] ?? 'pemilik')), 0, 40),
            'guardian_name' => $this->text($data['guardian_name'] ?? null, 120),
            'guardian_relation' => $this->text($data['guardian_relation'] ?? null, 40),
            'signature_path' => $this->text($data['signature_path'] ?? null, 255),
            'evidence_path' => $this->text($data['evidence_path'] ?? null, 255),
            'evidence_hash' => $this->text($data['evidence_hash'] ?? null, 64),
            'signed_at' => ! empty($data['signed_at']) ? \Illuminate\Support\Carbon::parse($data['signed_at']) : now(),
            'witness_name' => $this->text($data['witness_name'] ?? null, 120),
            'ip_address' => $data['ip_address'] ?? null,
        ])->refresh();
    }

    public function recordEmergencyConsent(MedicalRecord $record, array $data, User $actor): TreatmentConsent
    {
        $this->assertClinic($record->clinic_id, $actor);

        $basis = trim((string) ($data['emergency_basis'] ?? ''));

        if (! in_array($basis, TreatmentConsent::EMERGENCY_BASES, true)) {
            throw new MedicalRecordLifecycleException(
                'EMERGENCY_BASIS_REQUIRED',
                'Dasar tindakan darurat wajib dipilih.',
                422
            );
        }

        $signer = trim((string) ($data['signer_name'] ?? '')) ?: $actor->name;
        $template = $this->resolveTemplate($record->clinic_id, $data);

        return TreatmentConsent::create([
            'clinic_id' => $record->clinic_id,
            'medical_record_id' => $record->id,
            'pet_id' => $record->pet_id,
            'user_id' => $data['user_id'] ?? $record->pet?->user_id,
            'consent_type' => TreatmentConsent::TYPE_DARURAT,
            'template_key' => $template?->template_key,
            'template_version' => $template?->version,
            'consent_text_hash' => $template?->body_hash,
            'risks_explained' => $this->text($data['risks_explained'] ?? null),
            'signer_name' => mb_substr($signer, 0, 120),
            'signer_relation' => mb_substr(trim((string) ($data['signer_relation'] ?? 'petugas')), 0, 40),
            'guardian_name' => $this->text($data['guardian_name'] ?? null, 120),
            'guardian_relation' => $this->text($data['guardian_relation'] ?? null, 40),
            'signature_path' => $this->text($data['signature_path'] ?? null, 255),
            'evidence_path' => $this->text($data['evidence_path'] ?? null, 255),
            'evidence_hash' => $this->text($data['evidence_hash'] ?? null, 64),
            'signed_at' => ! empty($data['signed_at']) ? \Illuminate\Support\Carbon::parse($data['signed_at']) : now(),
            'witness_name' => $this->text($data['witness_name'] ?? null, 120),
            'ip_address' => $data['ip_address'] ?? null,
            'emergency_basis' => $basis,
        ])->refresh();
    }

    public function revoke(TreatmentConsent $consent, string $reason, User $actor): TreatmentConsent
    {
        $this->assertClinic($consent->clinic_id ?? $consent->record?->clinic_id, $actor);

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_REVOKE_REASON_REQUIRED',
                'Alasan pencabutan consent wajib diisi.',
                422
            );
        }

        if ($consent->revoked_at) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_ALREADY_REVOKED',
                'Consent ini sudah dicabut.',
                409
            );
        }

        $consent->forceFill([
            'revoked_at' => now(),
            'revoked_reason' => mb_substr($reason, 0, 255),
            'revoked_by' => $actor->id,
        ])->save();

        return $consent->refresh();
    }

    public function grantPdpConsent(User $owner, array $data, User $actor): Consent
    {
        if ($owner->id !== $actor->id && $actor->role !== 'admin') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_PDP_FORBIDDEN',
                'Persetujuan PDP hanya dapat diberikan oleh pemilik atau admin.',
                403
            );
        }

        $purpose = trim((string) ($data['purpose'] ?? ''));
        $policyVersion = trim((string) ($data['policy_version'] ?? ''));

        if ($purpose === '' || $policyVersion === '') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_PDP_INCOMPLETE',
                'Tujuan dan versi kebijakan wajib diisi.',
                422
            );
        }

        $policyText = trim((string) ($data['policy_text'] ?? ''));

        return Consent::create([
            'user_id' => $owner->id,
            'purpose' => mb_substr($purpose, 0, 60),
            'policy_version' => mb_substr($policyVersion, 0, 20),
            'policy_hash' => $policyText !== ''
                ? hash('sha256', $policyText)
                : $this->text($data['policy_hash'] ?? null, 64),
            'granted_at' => now(),
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $this->text($data['user_agent'] ?? null, 500),
            'evidence_path' => $this->text($data['evidence_path'] ?? null, 255),
        ])->refresh();
    }

    public function withdrawPdpConsent(Consent $consent, string $reason, User $actor): Consent
    {
        if ((int) $consent->user_id !== (int) $actor->id && $actor->role !== 'admin') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_PDP_FORBIDDEN',
                'Persetujuan PDP hanya dapat dicabut oleh pemilik atau admin.',
                403
            );
        }

        if ($consent->isWithdrawn()) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_PDP_ALREADY_WITHDRAWN',
                'Persetujuan PDP ini sudah dicabut.',
                409
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'CONSENT_PDP_REASON_REQUIRED',
                'Alasan pencabutan persetujuan PDP wajib diisi.',
                422
            );
        }

        $consent->forceFill([
            'withdrawn_at' => now(),
            'revoked_at' => now(),
            'withdrawal_reason' => mb_substr($reason, 0, 255),
        ])->save();

        return $consent->refresh();
    }

    private function resolveTemplate(int $clinicId, array $data): ?ConsentTemplate
    {
        $key = trim((string) ($data['template_key'] ?? ''));

        if ($key === '') {
            return null;
        }

        $version = isset($data['template_version']) && $data['template_version'] !== ''
            ? (int) $data['template_version']
            : null;

        $query = ConsentTemplate::where('clinic_id', $clinicId)->where('template_key', $key);

        if ($version !== null) {
            $query->where('version', $version);
        } else {
            $query->where('is_current', true);
        }

        $template = $query->first();

        if (! $template) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_TEMPLATE_NOT_FOUND',
                'Template consent yang dipilih tidak ditemukan.',
                404
            );
        }

        return $template;
    }

    private function text(mixed $value, int $max = 20000): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function assertClinic(?int $clinicId, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $clinicId) {
            throw new MedicalRecordLifecycleException(
                'CONSENT_CLINIC_MISMATCH',
                'Consent hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
