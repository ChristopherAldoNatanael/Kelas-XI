<?php

namespace App\Console\Commands;

use App\Models\NotifiableDiseaseReport;
use App\Services\NotifiableDiseaseService;
use Illuminate\Console\Command;

class EscalateOverdueDiseaseReports extends Command
{
    protected $signature = 'reports:escalate-overdue
        {--dry-run : Tampilkan tanpa menulis}';

    protected $description = 'Tandai laporan penyakit menular yang melewati tenggat 24 jam';

    public function handle(NotifiableDiseaseService $service): int
    {
        $query = NotifiableDiseaseReport::overdue()->whereNull('escalated_at');
        $count = (clone $query)->count();

        $this->info("Laporan lewat tenggat belum dieskalasi: {$count}");

        if ($count > 0) {
            $this->table(
                ['id', 'clinic_id', 'disease_code', 'status', 'deadline'],
                (clone $query)->orderBy('id')->limit(50)->get()->map(fn ($report) => [
                    $report->id,
                    $report->clinic_id,
                    $report->disease_code,
                    $report->status,
                    $report->report_deadline_at?->toDateTimeString(),
                ])->all()
            );
        }

        if ($this->option('dry-run') || $count === 0) {
            return self::SUCCESS;
        }

        $updated = $service->escalateOverdue();
        $this->info("Dieskalasi: {$updated}");

        return self::SUCCESS;
    }
}
