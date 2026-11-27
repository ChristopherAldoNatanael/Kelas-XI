<?php

namespace App\Console\Commands;

use App\Models\MedicalRecordCredentialSnapshot;
use Illuminate\Console\Command;

/**
 * Daftar kerja review kredensial historis. SLA 10 hari kerja dihitung dari
 * waktu finalisasi versi; command ini read-only.
 */
class ListCredentialReview extends Command
{
    protected $signature = 'medical-records:credential-review
        {--status=missing,exception : Status snapshot yang ditampilkan}
        {--json : Keluaran JSON}';

    protected $description = 'Daftar record dengan kredensial historis missing/exception';

    public function handle(): int
    {
        $statuses = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('status'))
        )));

        $rows = MedicalRecordCredentialSnapshot::query()
            ->with(['record:id,pet_id,doctor_id,finalized_at', 'version:id,version_no,finalized_at'])
            ->whereIn('credential_status', $statuses)
            ->orderBy('medical_record_id')
            ->orderBy('credential_type')
            ->get()
            ->map(function (MedicalRecordCredentialSnapshot $snapshot) {
                $finalizedAt = $snapshot->version?->finalized_at;
                $deadline = $finalizedAt ? $finalizedAt->copy()->addWeekdays(10) : null;
                $overdue = $deadline ? $deadline->lt(now()) : false;

                return [
                    'record_id' => $snapshot->medical_record_id,
                    'version_id' => $snapshot->version_id,
                    'version_no' => $snapshot->version?->version_no,
                    'finalized_at' => $finalizedAt?->toIso8601String(),
                    'credential_type' => $snapshot->credential_type,
                    'holder_type' => $snapshot->holder_type,
                    'holder_id' => $snapshot->holder_id,
                    'credential_status' => $snapshot->credential_status,
                    'review_deadline' => $deadline?->toDateString(),
                    'overdue' => $overdue,
                ];
            })
            ->values();

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => $rows->count(),
                'overdue' => $rows->where('overdue', true)->count(),
                'items' => $rows->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Snapshot menunggu review: {$rows->count()}");
        $this->info('Terlambat SLA: ' . $rows->where('overdue', true)->count());

        if ($rows->isNotEmpty()) {
            $this->table(
                ['record', 'version', 'finalized', 'type', 'status', 'deadline', 'overdue'],
                $rows->map(fn ($row) => [
                    $row['record_id'],
                    $row['version_no'],
                    $row['finalized_at'],
                    $row['credential_type'],
                    $row['credential_status'],
                    $row['review_deadline'],
                    $row['overdue'] ? 'YA' : 'tidak',
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
