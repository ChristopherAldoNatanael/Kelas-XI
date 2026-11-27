<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordExport;
use App\Models\MedicalRecordVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MedicalRecordExportService
{
    public const MAX_TTL_MINUTES = 1440;

    public function __construct(private AuditTrailService $audit)
    {
    }

    /**
     * @return array{export: MedicalRecordExport, token: string}
     */
    public function create(MedicalRecord $record, array $data, User $actor): array
    {
        $this->assertRecordClinic($record, $actor);

        $format = (string) ($data['format'] ?? MedicalRecordExport::FORMAT_PDF);
        $purpose = (string) ($data['purpose'] ?? MedicalRecordExport::PURPOSE_OWNER_COPY);

        if (! in_array($format, MedicalRecordExport::FORMATS, true)) {
            throw new MedicalRecordLifecycleException('EXPORT_FORMAT_INVALID', 'Format ekspor tidak valid.', 422);
        }

        if (! in_array($purpose, MedicalRecordExport::PURPOSES, true)) {
            throw new MedicalRecordLifecycleException('EXPORT_PURPOSE_INVALID', 'Tujuan ekspor tidak valid.', 422);
        }

        $ttl = (int) ($data['ttl_minutes'] ?? ($purpose === MedicalRecordExport::PURPOSE_OWNER_COPY ? 15 : 60));

        if ($ttl < 1 || $ttl > self::MAX_TTL_MINUTES) {
            throw new MedicalRecordLifecycleException(
                'EXPORT_TTL_INVALID',
                'Masa berlaku token harus 1 sampai 1440 menit.',
                422
            );
        }

        $versionId = $data['version_id'] ?? $record->current_version_id;

        if ($versionId) {
            $version = MedicalRecordVersion::whereKey($versionId)
                ->where('medical_record_id', $record->id)
                ->first();

            if (! $version) {
                throw new MedicalRecordLifecycleException(
                    'EXPORT_VERSION_MISMATCH',
                    'Versi rekam medis tidak berasal dari rekam medis ini.',
                    404
                );
            }
        }

        $token = $this->newToken();

        $export = MedicalRecordExport::create([
            'clinic_id' => $record->clinic_id,
            'medical_record_id' => $record->id,
            'version_id' => $versionId,
            'format' => $format,
            'purpose' => $purpose,
            'recipient_type' => $this->text($data['recipient_type'] ?? null, 40),
            'recipient_name' => $this->text($data['recipient_name'] ?? null, 150),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($ttl),
            'access_count' => 0,
            'file_hash' => $this->hashOrNull($data['file_hash'] ?? null),
            'created_by' => $actor->id,
        ])->refresh();

        $this->audit->record(
            'medical_record.export.created',
            $export,
            $actor,
            'allowed',
            null,
            [
                'format' => $export->format,
                'purpose' => $export->purpose,
                'version_id' => $export->version_id,
                'export_id' => $export->id,
                'record_id' => $export->medical_record_id,
                'recipient_type' => $export->recipient_type,
                'token_ttl_minutes' => $ttl,
            ],
            $export->clinic_id
        );

        return ['export' => $export, 'token' => $token];
    }

    public function render(MedicalRecordExport $export, array $data, User $actor): MedicalRecordExport
    {
        $record = $export->record;

        if (! $record) {
            throw new MedicalRecordLifecycleException('EXPORT_RECORD_MISSING', 'Rekam medis ekspor tidak ditemukan.', 404);
        }

        $this->assertRecordClinic($record, $actor);
        $this->assertMutable($export);

        $path = $this->text($data['rendered_path'] ?? null, 255);

        if ($path === null) {
            throw new MedicalRecordLifecycleException(
                'EXPORT_RENDERED_PATH_REQUIRED',
                'Path dokumen ekspor wajib diisi.',
                422
            );
        }

        $renderedHash = $this->requiredHash($data['rendered_hash'] ?? null, 'EXPORT_RENDERED_HASH_INVALID');
        $contentHash = $this->hashOrNull($data['content_hash'] ?? null);

        if ($contentHash === null) {
            $contentHash = $export->version?->content_hash ?: $record->content_hash;
        }

        $export->forceFill([
            'rendered_path' => $path,
            'rendered_hash' => $renderedHash,
            'content_hash' => $contentHash,
            'file_hash' => $renderedHash,
        ])->save();

        $export = $export->refresh();

        $this->audit->record(
            'medical_record.export.rendered',
            $export,
            $actor,
            'allowed',
            null,
            [
                'format' => $export->format,
                'purpose' => $export->purpose,
                'version_id' => $export->version_id,
                'export_id' => $export->id,
                'record_id' => $export->medical_record_id,
            ],
            $export->clinic_id
        );

        return $export;
    }

    public function sign(MedicalRecordExport $export, array $data, User $actor): MedicalRecordExport
    {
        $record = $export->record;

        if (! $record) {
            throw new MedicalRecordLifecycleException('EXPORT_RECORD_MISSING', 'Rekam medis ekspor tidak ditemukan.', 404);
        }

        $this->assertRecordClinic($record, $actor);
        $this->assertMutable($export);

        if ($actor->role !== 'doctor') {
            throw new MedicalRecordLifecycleException(
                'EXPORT_SIGNER_NOT_DOCTOR',
                'Hanya dokter yang boleh menandatangani ekspor resmi.',
                403
            );
        }

        if ($export->isSigned()) {
            throw new MedicalRecordLifecycleException(
                'EXPORT_ALREADY_SIGNED',
                'Ekspor sudah ditandatangani dan tidak dapat ditandatangani ulang.',
                422
            );
        }

        if (! $export->rendered_path || ! $export->rendered_hash) {
            throw new MedicalRecordLifecycleException(
                'EXPORT_DOCUMENT_NOT_READY',
                'Dokumen ekspor harus dibuat sebelum ditandatangani.',
                422
            );
        }

        $signaturePath = $this->text($data['signature_path'] ?? null, 255);

        if ($signaturePath === null) {
            throw new MedicalRecordLifecycleException(
                'EXPORT_SIGNATURE_PATH_REQUIRED',
                'Path tanda tangan pengesahan wajib diisi.',
                422
            );
        }

        $signatureHash = $this->hashOrNull($data['signature_hash'] ?? null);

        $export->forceFill([
            'signature_path' => $signaturePath,
            'signature_hash' => $signatureHash,
            'signed_at' => now(),
            'signed_by' => $actor->id,
        ])->save();

        $export = $export->refresh();

        $this->audit->record(
            'medical_record.export.signed',
            $export,
            $actor,
            'allowed',
            null,
            [
                'format' => $export->format,
                'purpose' => $export->purpose,
                'version_id' => $export->version_id,
                'export_id' => $export->id,
                'record_id' => $export->medical_record_id,
            ],
            $export->clinic_id
        );

        return $export;
    }

    public function resolve(
        string $token,
        ?int $medicalRecordId,
        ?int $versionId,
        ?string $format,
        ?string $purpose,
        ?string $ip,
        ?User $actor = null
    ): MedicalRecordExport {
        try {
            if ($token === '' || strlen($token) < 32) {
                throw new MedicalRecordLifecycleException('EXPORT_TOKEN_INVALID', 'Token ekspor tidak valid.', 404);
            }

            $export = DB::transaction(function () use ($token, $medicalRecordId, $versionId, $format, $purpose, $ip) {
                $export = MedicalRecordExport::where('token_hash', hash('sha256', $token))
                    ->lockForUpdate()
                    ->first();

                if (! $export) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_INVALID', 'Token ekspor tidak valid.', 404);
                }

                if ($export->isRevoked()) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_REVOKED', 'Token ekspor sudah dicabut.', 410);
                }

                if ($export->isExpired()) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_EXPIRED', 'Token ekspor sudah kedaluwarsa.', 410);
                }

                if ($export->isUsed() || $export->access_count >= 1) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_ALREADY_USED', 'Token ekspor hanya dapat dipakai sekali.', 410);
                }

                if ($medicalRecordId !== null && (int) $medicalRecordId !== (int) $export->medical_record_id) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_MISMATCH', 'Token tidak cocok dengan rekam medis.', 404);
                }

                if ($versionId !== null && (int) $versionId !== (int) $export->version_id) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_MISMATCH', 'Token tidak cocok dengan versi rekam medis.', 404);
                }

                if ($format !== null && $format !== $export->format) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_MISMATCH', 'Token tidak cocok dengan format ekspor.', 404);
                }

                if ($purpose !== null && $purpose !== $export->purpose) {
                    throw new MedicalRecordLifecycleException('EXPORT_TOKEN_MISMATCH', 'Token tidak cocok dengan tujuan ekspor.', 404);
                }

                if ($export->requiresSignature() && ! $export->isSigned()) {
                    throw new MedicalRecordLifecycleException(
                        'EXPORT_SIGNATURE_REQUIRED',
                        'Ekspor resmi harus ditandatangani dokter sebelum diunduh.',
                        409
                    );
                }

                $export->forceFill([
                    'token_used_at' => now(),
                    'downloaded_at' => now(),
                    'access_count' => $export->access_count + 1,
                    'last_accessed_at' => now(),
                    'last_accessed_ip' => $this->text($ip, 45),
                ])->save();

                return $export->refresh();
            });
        } catch (MedicalRecordLifecycleException $e) {
            $this->audit->record(
                'medical_record.export.access',
                null,
                $actor,
                'denied',
                $e->errorCode,
                [
                    'format' => $format,
                    'purpose' => $purpose,
                    'version_id' => $versionId,
                    'record_id' => $medicalRecordId,
                    'request_source' => 'token',
                ],
                null,
                $ip
            );

            throw $e;
        }

        $this->audit->record(
            'medical_record.export.access',
            $export,
            $actor,
            'allowed',
            null,
            [
                'format' => $export->format,
                'purpose' => $export->purpose,
                'version_id' => $export->version_id,
                'export_id' => $export->id,
                'record_id' => $export->medical_record_id,
                'access_count' => $export->access_count,
            ],
            $export->clinic_id,
            $ip
        );

        return $export;
    }

    public function revoke(MedicalRecordExport $export, ?string $reason, User $actor): MedicalRecordExport
    {
        $record = $export->record;

        if (! $record) {
            throw new MedicalRecordLifecycleException('EXPORT_RECORD_MISSING', 'Rekam medis ekspor tidak ditemukan.', 404);
        }

        $this->assertRecordClinic($record, $actor);

        if ($export->isRevoked()) {
            throw new MedicalRecordLifecycleException('EXPORT_ALREADY_REVOKED', 'Ekspor sudah dicabut.', 422);
        }

        if ($export->isUsed()) {
            throw new MedicalRecordLifecycleException('EXPORT_ALREADY_USED', 'Ekspor yang sudah diunduh tidak dapat dicabut.', 422);
        }

        $export->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
        ])->save();

        $export = $export->refresh();

        $this->audit->record(
            'medical_record.export.revoked',
            $export,
            $actor,
            'allowed',
            $reason === null ? null : mb_substr(trim($reason), 0, 120),
            [
                'format' => $export->format,
                'purpose' => $export->purpose,
                'version_id' => $export->version_id,
                'export_id' => $export->id,
                'record_id' => $export->medical_record_id,
            ],
            $export->clinic_id
        );

        return $export;
    }

    private function assertMutable(MedicalRecordExport $export): void
    {
        if ($export->isRevoked()) {
            throw new MedicalRecordLifecycleException('EXPORT_ALREADY_REVOKED', 'Ekspor sudah dicabut.', 422);
        }

        if ($export->isUsed()) {
            throw new MedicalRecordLifecycleException('EXPORT_ALREADY_USED', 'Ekspor yang sudah diunduh tidak dapat diubah.', 422);
        }
    }

    private function requiredHash(mixed $value, string $errorCode): string
    {
        $hash = $this->hashOrNull($value);

        if ($hash === null) {
            throw new MedicalRecordLifecycleException($errorCode, 'Hash SHA-256 wajib diisi.', 422);
        }

        return $hash;
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^[a-f0-9]{64}$/i', $value)) {
            throw new MedicalRecordLifecycleException('EXPORT_HASH_INVALID', 'Hash harus SHA-256 heksadesimal.', 422);
        }

        return strtolower($value);
    }

    private function assertRecordClinic(MedicalRecord $record, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $record->clinic_id) {
            throw new MedicalRecordLifecycleException(
                'EXPORT_CLINIC_MISMATCH',
                'Ekspor hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
