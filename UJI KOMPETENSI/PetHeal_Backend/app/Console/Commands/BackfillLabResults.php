<?php

namespace App\Console\Commands;

use App\Models\LabResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill hasil laboratorium lama.
 *
 * Tidak membuat order/item palsu. Hasil lama diberi status `legacy` karena
 * verifikasi dan spesimen aslinya tidak pernah tercatat. Verifikasi retroaktif
 * harus melalui alur koreksi resmi.
 */
class BackfillLabResults extends Command
{
    protected $signature = 'lab:backfill-results
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah baris per batch}';

    protected $description = 'Backfill clinic/species/status hasil laboratorium legacy';

    public function handle(): int
    {
        $query = LabResult::query()
            ->whereNull('clinic_id')
            ->whereNull('lab_order_id');

        $total = (clone $query)->count();
        $this->info("Hasil lab legacy tanpa clinic_id: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $updated = 0;
        $failed = [];

        $query->orderBy('id')->chunkById((int) $this->option('chunk'), function ($rows) use (&$updated, &$failed) {
            foreach ($rows as $result) {
                try {
                    DB::transaction(function () use ($result, &$updated) {
                        $record = DB::table('medical_records')
                            ->where('id', $result->medical_record_id)
                            ->first();

                        if (! $record) {
                            throw new \RuntimeException('Rekam medis tidak ditemukan.');
                        }

                        $species = DB::table('pets')->where('id', $record->pet_id)->value('species');

                        LabResult::whereKey($result->id)->update([
                            'clinic_id' => $record->clinic_id,
                            'species' => $species,
                            'reference_status' => LabResult::REFERENCE_UNAVAILABLE,
                            'status' => LabResult::STATUS_LEGACY,
                            'verified_at' => null,
                            'verified_by' => null,
                        ]);

                        $updated++;
                    });
                } catch (\Throwable $e) {
                    $failed[] = ['result_id' => $result->id, 'message' => $e->getMessage()];
                }
            }
        });

        $this->info("Hasil lab diperbarui: {$updated}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['result_id', 'message'], array_slice($failed, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
