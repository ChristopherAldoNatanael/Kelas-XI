<?php

namespace App\Console\Commands;

use App\Models\MedicalRecordRevision;
use App\Services\AuditChainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi prev_hash/entry_hash untuk revisi lama. Idempotent: baris yang sudah
 * memiliki entry_hash tidak ditulis ulang.
 */
class BackfillAuditChain extends Command
{
    protected $signature = 'audit:backfill-chain {--dry-run : Hitung tanpa menulis}';

    protected $description = 'Bangun hash berantai untuk revisi rekam medis lama';

    public function handle(AuditChainService $chain): int
    {
        $recordIds = MedicalRecordRevision::query()
            ->distinct()
            ->orderBy('medical_record_id')
            ->pluck('medical_record_id');

        $updated = 0;
        $issues = [];

        foreach ($recordIds as $recordId) {
            DB::transaction(function () use ($recordId, $chain, &$updated, &$issues) {
                $previousHash = AuditChainService::GENESIS;
                $rows = MedicalRecordRevision::where('medical_record_id', $recordId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($rows as $row) {
                    if ($row->entry_hash !== null) {
                        if ($row->prev_hash !== $previousHash) {
                            $issues[] = "record {$recordId} revision {$row->id}: prev_hash tidak cocok";
                        }

                        $previousHash = $row->entry_hash;
                        continue;
                    }

                    $row->prev_hash = $previousHash;
                    $row->entry_hash = $chain->entryHash($previousHash, $chain->payload($row));

                    if (! $this->option('dry-run')) {
                        $row->save();
                    }

                    $previousHash = $row->entry_hash;
                    $updated++;
                }
            });
        }

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . "Baris diberi hash: {$updated}");

        if ($issues !== []) {
            $this->error('Rantai yang sudah ada tidak konsisten: ' . count($issues));
            $this->table(['issue'], array_map(fn ($issue) => [$issue], array_slice($issues, 0, 50)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
