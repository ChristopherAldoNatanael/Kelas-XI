<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Doctor;
use App\Models\Paramedic;
use App\Models\ParamedicAuthorization;
use App\Models\ProfessionalCredential;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Otorisasi tindakan paramedik. Admin tidak dapat memberikan kewenangan klinis.
 */
class ParamedicAuthorizationService
{
    private const NONE_MODE_ACTIONS = [
        'documentation',
        'vital_signs',
        'specimen_collection',
        'non_invasive',
    ];

    public function __construct(private readonly ProfessionalCredentialService $credentials)
    {
    }

    public function authorizeAction(
        Paramedic $paramedic,
        string $actionCode,
        ?Carbon $at = null,
        bool $doctorConfirmed = false
    ): ParamedicAuthorization {
        $at = $at ?: now();

        if (! $paramedic->is_active) {
            throw new MedicalRecordLifecycleException(
                'PARAMEDIC_NOT_AUTHORIZED',
                'Paramedik tidak aktif.',
                403
            );
        }

        $authorization = ParamedicAuthorization::where('paramedic_id', $paramedic->id)
            ->where('clinic_id', $paramedic->clinic_id)
            ->where('action_code', $actionCode)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $at->toDateString())
            ->where(function ($query) use ($at) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $at->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $authorization) {
            throw new MedicalRecordLifecycleException(
                'PARAMEDIC_NOT_AUTHORIZED',
                "Tindakan {$actionCode} tidak diotorisasi untuk paramedik ini.",
                403
            );
        }

        $this->assertSipp($paramedic);

        $mode = $authorization->supervision_mode;

        if ($mode === ParamedicAuthorization::MODE_NONE
            && ! in_array($actionCode, self::NONE_MODE_ACTIONS, true)) {
            throw new MedicalRecordLifecycleException(
                'PARAMEDIC_NOT_AUTHORIZED',
                "Tindakan {$actionCode} memerlukan supervisi dokter.",
                403
            );
        }

        if (in_array($mode, [
            ParamedicAuthorization::MODE_AVAILABLE,
            ParamedicAuthorization::MODE_REAL_TIME,
        ], true)) {
            $supervisor = $paramedic->supervisingDoctor;

            if (! $supervisor || ! $supervisor->is_active
                || (int) $supervisor->clinic_id !== (int) $paramedic->clinic_id) {
                throw new MedicalRecordLifecycleException(
                    'PARAMEDIC_NOT_AUTHORIZED',
                    'Dokter penyelia tidak tersedia atau tidak aktif di klinik ini.',
                    403
                );
            }
        }

        if ($mode === ParamedicAuthorization::MODE_REAL_TIME && ! $doctorConfirmed) {
            throw new MedicalRecordLifecycleException(
                'PARAMEDIC_NOT_AUTHORIZED',
                "Tindakan {$actionCode} memerlukan konfirmasi dokter secara real-time.",
                403
            );
        }

        return $authorization;
    }

    public function issueAuthorization(
        Paramedic $paramedic,
        array $attributes,
        User $approver
    ): ParamedicAuthorization {
        $doctor = Doctor::where('user_id', $approver->id)->first();

        if ($approver->role !== 'doctor' || ! $doctor
            || (int) $doctor->clinic_id !== (int) $paramedic->clinic_id) {
            throw new MedicalRecordLifecycleException(
                'CLINICAL_APPROVER_REQUIRED',
                'Otorisasi paramedik hanya dapat diberikan dokter di klinik yang sama.',
                403
            );
        }

        $actionCode = (string) ($attributes['action_code'] ?? '');
        $mode = (string) ($attributes['supervision_mode'] ?? ParamedicAuthorization::MODE_NONE);

        if ($actionCode === '' || ! in_array($mode, [
            ParamedicAuthorization::MODE_NONE,
            ParamedicAuthorization::MODE_AVAILABLE,
            ParamedicAuthorization::MODE_REAL_TIME,
        ], true)) {
            throw new MedicalRecordLifecycleException(
                'INVALID_PARAMEDIC_AUTHORIZATION',
                'Action code atau mode supervisi tidak valid.',
                422
            );
        }

        return DB::transaction(function () use ($paramedic, $attributes, $approver, $doctor, $actionCode, $mode) {
            ParamedicAuthorization::where('paramedic_id', $paramedic->id)
                ->where('action_code', $actionCode)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(function (ParamedicAuthorization $old) {
                    $old->forceFill([
                        'is_active' => false,
                        'active_key' => null,
                    ])->save();
                });

            return ParamedicAuthorization::create([
                'clinic_id' => $paramedic->clinic_id,
                'paramedic_id' => $paramedic->id,
                'action_code' => $actionCode,
                'supervision_mode' => $mode,
                'effective_from' => $attributes['effective_from'] ?? today()->toDateString(),
                'effective_to' => $attributes['effective_to'] ?? null,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'is_active' => true,
                'active_key' => substr(sprintf(
                    'clinic:%d:paramedic:%d:action:%s',
                    $paramedic->clinic_id,
                    $paramedic->id,
                    strtolower($actionCode)
                ), 0, 160),
                'notes' => $attributes['notes'] ?? null,
            ]);
        });
    }

    private function assertSipp(Paramedic $paramedic): void
    {
        foreach ([
            ProfessionalCredential::TYPE_SIPP,
            ProfessionalCredential::TYPE_SIPP_KESWAN,
            ProfessionalCredential::TYPE_SIPP_INSEMINATOR,
            ProfessionalCredential::TYPE_SIPP_PKB,
            ProfessionalCredential::TYPE_SIPP_ATR,
        ] as $type) {
            $credential = $this->credentials->current(
                ProfessionalCredentialService::HOLDER_PARAMEDIC,
                (int) $paramedic->id,
                $type,
                (int) $paramedic->clinic_id
            );

            if ($credential && $this->credentials->statusOf($credential) === ProfessionalCredentialService::SNAPSHOT_VALID) {
                return;
            }
        }

        throw new MedicalRecordLifecycleException(
            'PARAMEDIC_NOT_AUTHORIZED',
            'SIPP paramedik tidak ada atau tidak berlaku.',
            403
        );
    }
}
