<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Services\RetentionService;
use Illuminate\Console\Command;

/**
 * Arsipkan rekam medis yang sudah melewati masa retensi.
 *
 * Tidak menghapus data. Legal hold selalu menahan arsip. Retention dihitung
 * dari finalized_at/created_at dan memakai masa retensi keuangan yang lebih
 * panjang bila pembayaran belum selesai.
 */
class ArchiveMedicalRecords extends Command
{
    protected $signature = 'medical-records:archive
                            {--dry-run : Tampilkan jumlah tanpa mengubah data}';

    protected $description = 'Arsipkan rekam medis yang melewati masa retensi (tidak menghapus data)';

    public function handle(RetentionService $retention): int
    {
        $query = MedicalRecord::query()
            ->where('status', MedicalRecord::STATUS_FINAL)
            ->where('legal_hold', false);

        $candidates = (clone $query)->count();
        $this->info("Rekam medis final tanpa legal hold: {$candidates}");

        if ($this->option('dry-run')) {
            $archivable = 0;
            $sample = [];

            (clone $query)->orderBy('id')->chunkById(200, function ($records) use ($retention, &$archivable, &$sample) {
                foreach ($records as $record) {
                    $until = $record->retention_until ?: $retention->retentionUntil($record);

                    if ($until->lte(now()->startOfDay())) {
                        $archivable++;

                        if (count($sample) < 20) {
                            $sample[] = [$record->id, $until->toDateString()];
                        }
                    }
                }
            });

            $this->info("{$archivable} rekam medis akan diarsipkan (dry run, tidak ada perubahan).");

            if ($sample !== []) {
                $this->table(['id', 'retention_until'], $sample);
            }

            return self::SUCCESS;
        }

        $archived = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(200, function ($records) use ($retention, &$archived, &$skipped) {
            foreach ($records as $record) {
                if ($record->retention_until === null) {
                    $retention->applyRetention($record);
                    $record->refresh();
                }

                if (! $retention->canArchive($record)) {
                    $skipped++;
                    continue;
                }

                try {
                    $retention->archive($record);
                    $archived++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $this->warn("Rekam medis #{$record->id} dilewati: {$e->getMessage()}");
                }
            }
        });

        $this->info("Diarsipkan: {$archived}. Dilewati: {$skipped}.");

        return self::SUCCESS;
    }
}
