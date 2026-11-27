<?php

namespace App\Console\Commands;

use App\Models\MedicalRecordRevision;
use App\Services\AuditChainService;
use Illuminate\Console\Command;

class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify-chain {--record= : Hanya rekam medis ini} {--json : Keluaran JSON}';

    protected $description = 'Verifikasi hash berantai revisi rekam medis';

    public function handle(AuditChainService $chain): int
    {
        $query = MedicalRecordRevision::query()->orderBy('medical_record_id')->orderBy('id');

        if ($this->option('record')) {
            $query->where('medical_record_id', (int) $this->option('record'));
        }

        $issues = [];
        $checked = 0;
        $currentRecord = null;
        $previousHash = AuditChainService::GENESIS;

        foreach ($query->cursor() as $row) {
            if ($currentRecord !== $row->medical_record_id) {
                $currentRecord = $row->medical_record_id;
                $previousHash = AuditChainService::GENESIS;
            }

            $checked++;

            if ($row->entry_hash === null || $row->prev_hash === null) {
                $issues[] = [
                    'record_id' => $row->medical_record_id,
                    'revision_id' => $row->id,
                    'error' => 'MISSING_HASH',
                ];
            } else {
                if (! hash_equals($previousHash, (string) $row->prev_hash)) {
                    $issues[] = [
                        'record_id' => $row->medical_record_id,
                        'revision_id' => $row->id,
                        'error' => 'BROKEN_PREV_HASH',
                    ];
                }

                $expected = $chain->entryHash($previousHash, $chain->payload($row));

                if (! hash_equals($expected, (string) $row->entry_hash)) {
                    $issues[] = [
                        'record_id' => $row->medical_record_id,
                        'revision_id' => $row->id,
                        'error' => 'ENTRY_HASH_MISMATCH',
                    ];
                }
            }

            $previousHash = (string) ($row->entry_hash ?: '');
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'checked' => $checked,
                'issues' => $issues,
                'valid' => $issues === [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $issues === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Baris diperiksa: {$checked}");
        $this->info('Masalah: ' . count($issues));

        if ($issues !== []) {
            $this->table(
                ['record_id', 'revision_id', 'error'],
                array_map(fn ($issue) => [$issue['record_id'], $issue['revision_id'], $issue['error']], array_slice($issues, 0, 50))
            );

            return self::FAILURE;
        }

        $this->info('Rantai audit valid.');

        return self::SUCCESS;
    }
}
