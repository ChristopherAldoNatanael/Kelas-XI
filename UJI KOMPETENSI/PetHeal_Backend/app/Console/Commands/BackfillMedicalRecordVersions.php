<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Services\MedicalRecordVersionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * M4 — versi 1 untuk rekam medis lama yang sudah final/void/archived.
 *
 * Draf tidak dibuatkan versi. Hash lama disimpan sebagai
 * `legacy_content_hash`; hash baru adalah hash kanonik snapshot.
 */
class BackfillMedicalRecordVersions extends Command
{
    protected $signature = 'medical-records:backfill-versions
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah baris per batch}';

    protected $description = 'Buat versi 1 immutable untuk rekam medis lama';

    public function handle(MedicalRecordVersionService $versions): int
    {
        $query = MedicalRecord::query()
            ->whereNull('current_version_id')
            ->whereIn('status', [
                MedicalRecord::STATUS_FINAL,
                MedicalRecord::STATUS_VOID,
                MedicalRecord::STATUS_ARCHIVED,
            ])
            ->orderBy('id');

        $total = (clone $query)->count();
        $this->info("Rekam medis tanpa versi: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $created = 0;
        $failed = [];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(max(20, (int) $this->option('chunk')), function ($records) use ($versions, &$created, &$failed, $bar) {
            foreach ($records as $record) {
                try {
                    $versions->createVersion($record, $record->finalized_by, null, $record->content_hash);
                    $created++;
                } catch (Throwable $e) {
                    $failed[] = ['record_id' => $record->id, 'message' => $e->getMessage()];
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Versi dibuat: {$created}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['record_id', 'message'], $failed);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
