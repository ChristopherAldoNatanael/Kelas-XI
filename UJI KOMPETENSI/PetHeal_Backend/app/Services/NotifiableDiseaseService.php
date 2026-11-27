<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\MedicalRecord;
use App\Models\NotifiableDiseaseReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan kewajiban pelaporan penyakit menular.
 *
 * PetHeal tidak mengklaim integrasi API iSIKHNAS. Service ini hanya mencatat
 * indikasi, tenggat 24 jam, jalur pelaporan resmi, bukti, dan verifikasi.
 */
class NotifiableDiseaseService
{
    public function create(MedicalRecord $record, array $data, User $actor): NotifiableDiseaseReport
    {
        $this->assertRecordClinic($record, $actor);

        if (! $record->encounter_id) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_ENCOUNTER_REQUIRED',
                'Laporan penyakit menular memerlukan encounter klinis.',
                422
            );
        }

        $code = strtoupper(trim((string) ($data['disease_code'] ?? '')));

        if ($code === '' || ! preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,59}$/', $code)) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_CODE_REQUIRED',
                'Kode penyakit wajib diisi dan hanya boleh memakai huruf, angka, titik, garis, atau slash.',
                422
            );
        }

        $name = trim((string) ($data['disease_name'] ?? ''));

        if ($name === '') {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_NAME_REQUIRED',
                'Nama penyakit wajib diisi.',
                422
            );
        }

        $classification = (string) ($data['classification'] ?? '');

        if (! in_array($classification, NotifiableDiseaseReport::CLASSIFICATIONS, true)) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_CLASSIFICATION_INVALID',
                'Klasifikasi harus suspected, probable, atau confirmed.',
                422
            );
        }

        $indicatedAt = ! empty($data['indicated_at'])
            ? Carbon::parse($data['indicated_at'])
            : now();

        if ($indicatedAt->isFuture()) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_INDICATED_IN_FUTURE',
                'Waktu indikasi tidak boleh di masa depan.',
                422
            );
        }

        $hours = max(1, (int) config('medical_records.notifiable_report_deadline_hours', 24));

        return NotifiableDiseaseReport::create([
            'clinic_id' => $record->clinic_id,
            'medical_record_id' => $record->id,
            'encounter_id' => $record->encounter_id,
            'disease_code' => $code,
            'disease_name' => mb_substr($name, 0, 150),
            'classification' => $classification,
            'indicated_at' => $indicatedAt,
            'report_deadline_at' => $indicatedAt->copy()->addHours($hours),
            'status' => NotifiableDiseaseReport::STATUS_DRAFT,
            'notes' => $this->text($data['notes'] ?? null),
        ])->refresh();
    }

    public function markReady(NotifiableDiseaseReport $report, User $actor): NotifiableDiseaseReport
    {
        $this->assertRowClinic($report, $actor);
        $this->assertNotCancelled($report);

        if ($report->status !== NotifiableDiseaseReport::STATUS_DRAFT) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_STATUS_INVALID',
                'Hanya laporan draft yang dapat ditandai siap.',
                422
            );
        }

        $report->forceFill(['status' => NotifiableDiseaseReport::STATUS_READY])->save();

        return $report->refresh();
    }

    public function markReported(
        NotifiableDiseaseReport $report,
        array $data,
        User $actor
    ): NotifiableDiseaseReport {
        $this->assertRowClinic($report, $actor);
        $this->assertNotCancelled($report);

        if ($report->status !== NotifiableDiseaseReport::STATUS_READY) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_STATUS_INVALID',
                'Laporan harus berstatus ready sebelum ditandai reported.',
                422
            );
        }

        $reference = $this->text($data['isikhnas_reference'] ?? null, 120);
        $evidence = $this->text($data['evidence_path'] ?? null, 255);

        if ($reference === null && $evidence === null) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_EVIDENCE_REQUIRED',
                'Isi nomor referensi iSIKHNAS atau bukti pelaporan resmi.',
                422
            );
        }

        return DB::transaction(function () use ($report, $data, $actor, $reference, $evidence) {
            $locked = NotifiableDiseaseReport::whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== NotifiableDiseaseReport::STATUS_READY) {
                throw new MedicalRecordLifecycleException(
                    'NOTIFIABLE_STATUS_INVALID',
                    'Status laporan sudah berubah. Muat ulang data.',
                    409
                );
            }

            $reportedAt = ! empty($data['reported_at'])
                ? Carbon::parse($data['reported_at'])
                : now();

            if ($reportedAt->isFuture()) {
                throw new MedicalRecordLifecycleException(
                    'NOTIFIABLE_REPORTED_IN_FUTURE',
                    'Waktu pelaporan tidak boleh di masa depan.',
                    422
                );
            }

            $locked->forceFill([
                'status' => NotifiableDiseaseReport::STATUS_REPORTED,
                'reported_at' => $reportedAt,
                'reported_by' => $actor->id,
                'isikhnas_reference' => $reference,
                'evidence_path' => $evidence,
                'notes' => $this->mergeNotes($locked->notes, $data['notes'] ?? null),
            ])->save();

            return $locked->refresh();
        });
    }

    public function verify(NotifiableDiseaseReport $report, User $actor): NotifiableDiseaseReport
    {
        $this->assertRowClinic($report, $actor);
        $this->assertNotCancelled($report);

        if ($actor->role !== 'doctor') {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_VERIFIER_NOT_DOCTOR',
                'Hanya dokter yang boleh memverifikasi laporan penyakit menular.',
                403
            );
        }

        if ($report->status !== NotifiableDiseaseReport::STATUS_REPORTED) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_STATUS_INVALID',
                'Hanya laporan reported yang dapat diverifikasi.',
                422
            );
        }

        $report->forceFill([
            'status' => NotifiableDiseaseReport::STATUS_VERIFIED,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ])->save();

        return $report->refresh();
    }

    public function cancel(NotifiableDiseaseReport $report, string $reason, User $actor): NotifiableDiseaseReport
    {
        $this->assertRowClinic($report, $actor);
        $this->assertNotCancelled($report);

        if ($report->status === NotifiableDiseaseReport::STATUS_VERIFIED) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_VERIFIED_IMMUTABLE',
                'Laporan terverifikasi tidak dapat dibatalkan; buat koreksi baru.',
                422
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_CANCEL_REASON_REQUIRED',
                'Alasan pembatalan laporan wajib diisi.',
                422
            );
        }

        $report->forceFill([
            'status' => NotifiableDiseaseReport::STATUS_CANCELLED,
            'cancel_reason' => mb_substr($reason, 0, 20000),
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
        ])->save();

        return $report->refresh();
    }

    public function escalateOverdue(): int
    {
        $count = 0;

        NotifiableDiseaseReport::overdue()
            ->whereNull('escalated_at')
            ->orderBy('id')
            ->chunkById(200, function ($reports) use (&$count) {
                foreach ($reports as $report) {
                    $report->forceFill(['escalated_at' => now()])->save();
                    $count++;
                }
            });

        return $count;
    }

    private function mergeNotes(?string $existing, mixed $new): ?string
    {
        $new = $this->text($new);

        if ($new === null) {
            return $existing;
        }

        return $existing === null ? $new : $existing . "\n" . $new;
    }

    private function assertNotCancelled(NotifiableDiseaseReport $report): void
    {
        if ($report->status === NotifiableDiseaseReport::STATUS_CANCELLED) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_CANCELLED',
                'Laporan sudah dibatalkan dan tidak dapat diubah.',
                422
            );
        }
    }

    private function assertRecordClinic(MedicalRecord $record, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $record->clinic_id) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_CLINIC_MISMATCH',
                'Laporan hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }

    private function assertRowClinic(NotifiableDiseaseReport $report, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $report->clinic_id) {
            throw new MedicalRecordLifecycleException(
                'NOTIFIABLE_CLINIC_MISMATCH',
                'Laporan hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }

    private function text(mixed $value, ?int $max = null): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $max === null ? mb_substr($value, 0, 20000) : mb_substr($value, 0, $max);
    }
}
