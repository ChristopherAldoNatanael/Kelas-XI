<?php

namespace App\Console\Commands;

use App\Models\TreatmentConsent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi clinic_id consent tindakan lama dari rekam medisnya.
 * Tidak mengarang template, hash, atau bukti tanda tangan.
 */
class BackfillTreatmentConsents extends Command
{
    protected $signature = 'consents:backfill-treatment
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah baris per batch}';

    protected $description = 'Backfill clinic_id consent tindakan lama';

    public function handle(): int
    {
        $query = TreatmentConsent::whereNull('clinic_id')->whereNotNull('medical_record_id');

        $total = (clone $query)->count();
        $this->info("Consent tindakan tanpa clinic_id: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $updated = 0;
        $failed = [];

        $query->orderBy('id')->chunkById((int) $this->option('chunk'), function ($rows) use (&$updated, &$failed) {
            foreach ($rows as $consent) {
                try {
                    DB::transaction(function () use ($consent, &$updated) {
                        $clinicId = DB::table('medical_records')
                            ->where('id', $consent->medical_record_id)
                            ->value('clinic_id');

                        if (! $clinicId) {
                            throw new \RuntimeException('Rekam medis tidak memiliki clinic_id.');
                        }

                        TreatmentConsent::whereKey($consent->id)->update([
                            'clinic_id' => $clinicId,
                        ]);
                        $updated++;
                    });
                } catch (\Throwable $e) {
                    $failed[] = ['consent_id' => $consent->id, 'message' => $e->getMessage()];
                }
            }
        });

        $this->info("Consent diperbarui: {$updated}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['consent_id', 'message'], array_slice($failed, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
