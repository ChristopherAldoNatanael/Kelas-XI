<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordCredentialSnapshot;
use App\Models\Paramedic;
use App\Models\ProfessionalCredential;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sumber kebenaran kredensial tenaga dan snapshot per versi rekam medis.
 *
 * Snapshot bersifat append-only dan tidak pernah diperbarui. Perubahan SIP/STR
 * hanya memengaruhi dokumen baru, bukan dokumen yang sudah final.
 */
class ProfessionalCredentialService
{
    public const HOLDER_DOCTOR = 'doctor';
    public const HOLDER_PARAMEDIC = 'paramedic';
    public const HOLDER_CLINIC = 'clinic';

    public const SNAPSHOT_VALID = 'valid';
    public const SNAPSHOT_EXPIRED = 'expired';
    public const SNAPSHOT_MISSING = 'missing';
    public const SNAPSHOT_EXCEPTION = 'exception';

    public const DOCTOR_REQUIRED_TYPES = [
        ProfessionalCredential::TYPE_STR,
        ProfessionalCredential::TYPE_SIP,
    ];

    public function issue(array $attributes, ?User $actor = null): ProfessionalCredential
    {
        $clinicId = (int) ($attributes['clinic_id'] ?? 0);
        $doctorId = $attributes['doctor_id'] ?? null;
        $paramedicId = $attributes['paramedic_id'] ?? null;
        $type = (string) ($attributes['credential_type'] ?? '');

        if ($clinicId <= 0) {
            throw new InvalidArgumentException('clinic_id wajib diisi.');
        }

        if (($doctorId === null) === ($paramedicId === null)) {
            throw new InvalidArgumentException('Pilih tepat satu pemegang kredensial: dokter atau paramedik.');
        }

        if (! in_array($type, $this->allowedTypes(), true)) {
            throw new InvalidArgumentException('Jenis kredensial tidak valid.');
        }

        return DB::transaction(function () use ($attributes, $clinicId, $doctorId, $paramedicId, $type, $actor) {
            $holderType = $doctorId !== null ? self::HOLDER_DOCTOR : self::HOLDER_PARAMEDIC;
            $holderId = (int) ($doctorId ?? $paramedicId);

            if ($holderType === self::HOLDER_DOCTOR) {
                $doctor = Doctor::whereKey($holderId)->lockForUpdate()->firstOrFail();
                if ((int) $doctor->clinic_id !== $clinicId) {
                    throw new InvalidArgumentException('Dokter bukan anggota klinik ini.');
                }
            } else {
                $paramedic = Paramedic::whereKey($holderId)->lockForUpdate()->firstOrFail();
                if ((int) $paramedic->clinic_id !== $clinicId) {
                    throw new InvalidArgumentException('Paramedik bukan anggota klinik ini.');
                }
            }

            $scope = $this->normalizeScope($attributes['practice_scope'] ?? null);
            $expires = $this->dateOrNull($attributes['expires_at'] ?? null);
            $status = (string) ($attributes['status'] ?? ProfessionalCredential::STATUS_PENDING);

            if ($status === ProfessionalCredential::STATUS_ACTIVE && $expires && $expires->lt(today())) {
                $status = ProfessionalCredential::STATUS_EXPIRED;
            }

            if (($attributes['number'] ?? '') === '') {
                throw new InvalidArgumentException('Nomor kredensial wajib diisi.');
            }

            $isCurrent = (bool) ($attributes['is_current'] ?? true);

            if ($isCurrent) {
                ProfessionalCredential::where('clinic_id', $clinicId)
                    ->where('credential_type', $type)
                    ->where(function ($query) use ($doctorId, $paramedicId) {
                        if ($doctorId !== null) {
                            $query->where('doctor_id', $doctorId);
                        } else {
                            $query->where('paramedic_id', $paramedicId);
                        }
                    })
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (ProfessionalCredential $old) {
                        $old->forceFill([
                            'is_current' => false,
                            'active_key' => null,
                            'superseded_at' => now(),
                        ])->save();
                    });
            }

            $credential = ProfessionalCredential::create([
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorId,
                'paramedic_id' => $paramedicId,
                'credential_type' => $type,
                'number' => (string) $attributes['number'],
                'issued_at' => $this->dateOrNull($attributes['issued_at'] ?? null),
                'expires_at' => $expires,
                'practice_scope' => $attributes['practice_scope'] ?? null,
                'status' => $status,
                'verified_at' => $attributes['verified_at'] ?? ($status === ProfessionalCredential::STATUS_ACTIVE ? now() : null),
                'verified_by' => $attributes['verified_by'] ?? $actor?->id,
                'document_path' => $attributes['document_path'] ?? null,
                'is_current' => $isCurrent,
                'active_key' => $isCurrent && $status === ProfessionalCredential::STATUS_ACTIVE
                    ? $this->activeKey($holderType, $holderId, $type, $clinicId, $scope)
                    : null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            if ($holderType === self::HOLDER_DOCTOR) {
                $this->refreshDoctorCache((int) $doctorId);
            }

            return $credential;
        });
    }

    public function current(
        string $holderType,
        int $holderId,
        string $type,
        int $clinicId
    ): ?ProfessionalCredential {
        $query = ProfessionalCredential::where('clinic_id', $clinicId)
            ->where('credential_type', $type)
            ->where('is_current', true);

        if ($holderType === self::HOLDER_DOCTOR) {
            $query->where('doctor_id', $holderId);
        } else {
            $query->where('paramedic_id', $holderId);
        }

        return $query->orderByDesc('expires_at')->orderByDesc('id')->first();
    }

    /**
     * Periksa kredensial untuk finalisasi normal. Emergency tidak memanggil
     * method ini dan tidak mengubah status kredensial.
     */
    public function assertCanFinalize(MedicalRecord $record): array
    {
        $evaluation = $this->evaluateFinalization($record);

        if (! $evaluation['allowed']) {
            $first = $evaluation['issues'][0];

            throw new MedicalRecordLifecycleException(
                $first['code'],
                $first['message'],
                422,
                ['errors' => $evaluation['issues']]
            );
        }

        return $evaluation['snapshots'];
    }

    public function evaluateFinalization(MedicalRecord $record): array
    {
        $record->loadMissing(['doctor', 'clinic']);

        $snapshots = [];
        $issues = [];

        $doctor = $record->doctor;

        if (! $doctor) {
            $issues[] = [
                'field' => 'doctor_id',
                'code' => 'CREDENTIAL_MISSING',
                'credential_type' => 'DOCTOR',
                'status' => self::SNAPSHOT_MISSING,
                'message' => 'Rekam medis tidak memiliki dokter penanggung jawab.',
            ];
        } else {
            foreach (self::DOCTOR_REQUIRED_TYPES as $type) {
                $credential = $this->current(self::HOLDER_DOCTOR, (int) $doctor->id, $type, (int) $record->clinic_id);
                $snapshot = $this->credentialSnapshot(
                    self::HOLDER_DOCTOR,
                    (int) $doctor->id,
                    $type,
                    (int) $record->clinic_id
                );
                $snapshots[] = $snapshot;

                if ($snapshot['credential_status'] !== self::SNAPSHOT_VALID) {
                    $issues[] = [
                        'field' => strtolower($type),
                        'code' => $snapshot['credential_status'] === self::SNAPSHOT_EXPIRED
                            ? 'CREDENTIAL_EXPIRED'
                            : 'CREDENTIAL_MISSING',
                        'credential_type' => $type,
                        'status' => $snapshot['credential_status'],
                        'message' => $this->issueMessage($type, $snapshot),
                    ];
                }
            }
        }

        $clinicSnapshot = $this->clinicLicenseSnapshot((int) $record->clinic_id);
        $snapshots[] = $clinicSnapshot;

        if ($clinicSnapshot['credential_status'] !== self::SNAPSHOT_VALID) {
            $issues[] = [
                'field' => 'clinic_license',
                'code' => $clinicSnapshot['credential_status'] === self::SNAPSHOT_EXPIRED
                    ? 'CREDENTIAL_EXPIRED'
                    : 'CREDENTIAL_MISSING',
                'credential_type' => 'CLINIC_LICENSE',
                'status' => $clinicSnapshot['credential_status'],
                'message' => $this->issueMessage('CLINIC_LICENSE', $clinicSnapshot),
            ];
        }

        return [
            'allowed' => $issues === [],
            'issues' => $issues,
            'snapshots' => $snapshots,
        ];
    }

    public function credentialSnapshot(
        string $holderType,
        int $holderId,
        string $type,
        int $clinicId
    ): array {
        $credential = $this->current($holderType, $holderId, $type, $clinicId);

        if (! $credential) {
            return [
                'holder_type' => $holderType,
                'holder_id' => $holderId,
                'credential_type' => $type,
                'number' => null,
                'expires_at' => null,
                'practice_scope' => null,
                'credential_status' => self::SNAPSHOT_MISSING,
                'verified_at' => null,
                'verified_by' => null,
                'snapshot' => [],
            ];
        }

        return [
            'holder_type' => $holderType,
            'holder_id' => $holderId,
            'credential_type' => $type,
            'number' => $credential->number,
            'expires_at' => $credential->expires_at?->toDateString(),
            'practice_scope' => $credential->practice_scope,
            'credential_status' => $this->statusOf($credential),
            'verified_at' => $credential->verified_at,
            'verified_by' => $credential->verified_by,
            'snapshot' => [
                'id' => $credential->id,
                'clinic_id' => $credential->clinic_id,
                'credential_type' => $credential->credential_type,
                'number' => $credential->number,
                'issued_at' => $credential->issued_at?->toDateString(),
                'expires_at' => $credential->expires_at?->toDateString(),
                'practice_scope' => $credential->practice_scope,
                'status' => $credential->status,
                'verified_at' => $credential->verified_at?->toIso8601String(),
                'verified_by' => $credential->verified_by,
                'document_path' => $credential->document_path,
                'is_current' => $credential->is_current,
            ],
        ];
    }

    public function clinicLicenseSnapshot(int $clinicId): array
    {
        $clinic = Clinic::find($clinicId);

        if (! $clinic || ! $clinic->license_number) {
            return [
                'holder_type' => self::HOLDER_CLINIC,
                'holder_id' => $clinicId,
                'credential_type' => 'CLINIC_LICENSE',
                'number' => null,
                'expires_at' => null,
                'practice_scope' => null,
                'credential_status' => self::SNAPSHOT_MISSING,
                'verified_at' => null,
                'verified_by' => null,
                'snapshot' => [],
            ];
        }

        $expired = $clinic->license_expiry && $clinic->license_expiry->lt(today());

        return [
            'holder_type' => self::HOLDER_CLINIC,
            'holder_id' => $clinicId,
            'credential_type' => 'CLINIC_LICENSE',
            'number' => $clinic->license_number,
            'expires_at' => $clinic->license_expiry?->toDateString(),
            'practice_scope' => $clinic->name,
            'credential_status' => $expired ? self::SNAPSHOT_EXPIRED : self::SNAPSHOT_VALID,
            'verified_at' => null,
            'verified_by' => null,
            'snapshot' => [
                'clinic_id' => $clinic->id,
                'name' => $clinic->name,
                'license_number' => $clinic->license_number,
                'license_expiry' => $clinic->license_expiry?->toDateString(),
                'responsible_doctor_id' => $clinic->responsible_doctor_id,
            ],
        ];
    }

    public function captureSnapshots(
        MedicalRecord $record,
        ?int $versionId,
        ?User $actor = null
    ): array {
        $evaluation = $this->evaluateFinalization($record);
        $created = [];

        foreach ($evaluation['snapshots'] as $snapshot) {
            $created[] = MedicalRecordCredentialSnapshot::firstOrCreate(
                [
                    'version_id' => $versionId,
                    'holder_type' => $snapshot['holder_type'],
                    'holder_id' => $snapshot['holder_id'],
                    'credential_type' => $snapshot['credential_type'],
                ],
                [
                    'clinic_id' => $record->clinic_id,
                    'medical_record_id' => $record->id,
                    'number' => $snapshot['number'],
                    'expires_at' => $snapshot['expires_at'],
                    'practice_scope' => $snapshot['practice_scope'],
                    'credential_status' => $snapshot['credential_status'],
                    'verified_at' => $snapshot['verified_at'],
                    'verified_by' => $snapshot['verified_by'],
                    'snapshot' => $snapshot['snapshot'],
                    'created_at' => now(),
                ]
            );
        }

        return $created;
    }

    /**
     * Snapshot kredensial reviewer saat menyetujui amendment klinis.
     */
    public function reviewerSnapshot(User $reviewer): array
    {
        $doctor = Doctor::where('user_id', $reviewer->id)->first();

        if (! $doctor) {
            return [
                'status' => self::SNAPSHOT_MISSING,
                'doctor_id' => null,
                'credentials' => [],
            ];
        }

        $rows = [];
        foreach (self::DOCTOR_REQUIRED_TYPES as $type) {
            $rows[] = $this->credentialSnapshot(
                self::HOLDER_DOCTOR,
                (int) $doctor->id,
                $type,
                (int) $doctor->clinic_id
            );
        }

        $status = collect($rows)->every(fn ($row) => $row['credential_status'] === self::SNAPSHOT_VALID)
            ? self::SNAPSHOT_VALID
            : self::SNAPSHOT_EXCEPTION;

        return [
            'status' => $status,
            'doctor_id' => $doctor->id,
            'credentials' => $rows,
        ];
    }

    public function statusOf(ProfessionalCredential $credential): string
    {
        if ($credential->isExpired() || $credential->status === ProfessionalCredential::STATUS_EXPIRED) {
            return self::SNAPSHOT_EXPIRED;
        }

        if (in_array($credential->status, [
            ProfessionalCredential::STATUS_REVOKED,
            ProfessionalCredential::STATUS_PENDING,
        ], true)) {
            return self::SNAPSHOT_EXCEPTION;
        }

        return $credential->status === ProfessionalCredential::STATUS_ACTIVE
            ? self::SNAPSHOT_VALID
            : self::SNAPSHOT_EXCEPTION;
    }

    public function activeKey(
        string $holderType,
        int $holderId,
        string $type,
        int $clinicId,
        ?string $scope = null
    ): string {
        return substr(sprintf(
            '%s:%d:%s:clinic:%d:%s',
            $holderType,
            $holderId,
            strtoupper($type),
            $clinicId,
            $this->normalizeScope($scope)
        ), 0, 180);
    }

    public function allowedTypes(): array
    {
        return [
            ProfessionalCredential::TYPE_STR,
            ProfessionalCredential::TYPE_SIP,
            ProfessionalCredential::TYPE_SIPP,
            ProfessionalCredential::TYPE_SIPP_KESWAN,
            ProfessionalCredential::TYPE_SIPP_INSEMINATOR,
            ProfessionalCredential::TYPE_SIPP_PKB,
            ProfessionalCredential::TYPE_SIPP_ATR,
        ];
    }

    private function refreshDoctorCache(int $doctorId): void
    {
        $doctor = Doctor::find($doctorId);

        if (! $doctor) {
            return;
        }

        $sip = $this->current(self::HOLDER_DOCTOR, $doctorId, ProfessionalCredential::TYPE_SIP, (int) $doctor->clinic_id);
        $status = $sip ? $this->statusOf($sip) : self::SNAPSHOT_MISSING;

        $doctor->forceFill([
            'credential_status' => $status,
            'credential_verified_at' => $sip?->verified_at,
        ])->save();
    }

    private function issueMessage(string $type, array $snapshot): string
    {
        if ($snapshot['credential_status'] === self::SNAPSHOT_MISSING) {
            return "{$type} belum tercatat untuk tenaga/klinik ini.";
        }

        if ($snapshot['credential_status'] === self::SNAPSHOT_EXPIRED) {
            $date = $snapshot['expires_at'] ?: 'tanggal tidak diketahui';

            return "{$type} berakhir {$date}.";
        }

        return "{$type} tidak dalam status sah (exception).";
    }

    private function normalizeScope(?string $scope): string
    {
        $scope = Str::lower(trim((string) $scope));

        return $scope === '' ? 'general' : substr(preg_replace('/[^a-z0-9]+/', '-', $scope), 0, 80);
    }

    private function dateOrNull(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }
}
