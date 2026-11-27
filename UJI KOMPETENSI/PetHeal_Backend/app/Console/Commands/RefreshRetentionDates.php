<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Services\RetentionService;
use Illuminate\Console\Command;

class RefreshRetentionDates extends Command
{
    protected $signature = 'medical-records:refresh-retention
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah baris per batch}';

    protected $description = 'Hitung ulang retention_until sesuai kebijakan retensi';

    public function handle(RetentionService $retention): int
    {
        $total = MedicalRecord::count();
        $this->info("Rekam medis diperiksa: {$total}");

        if ($this->option('dry-run')) {
            $sample = MedicalRecord::orderBy('id')->limit(20)->get()->map(fn ($record) => [
                $record->id,
                optional($record->created_at)->toDateString(),
                $retention->retentionUntil($record)->toDateString(),
                $record->legal_hold ? 'ya' : 'tidak',
            ]);

            $this->table(['id', 'dibuat', 'retention_until', 'legal_hold'], $sample->all());

            return self::SUCCESS;
        }

        $updated = 0;

        MedicalRecord::orderBy('id')->chunkById((int) $this->option('chunk'), function ($records) use ($retention, &$updated) {
            foreach ($records as $record) {
                $retention->applyRetention($record);
                $updated++;
            }
        });

        $this->info("retention_until diperbarui: {$updated}");

        return self::SUCCESS;
    }
}
