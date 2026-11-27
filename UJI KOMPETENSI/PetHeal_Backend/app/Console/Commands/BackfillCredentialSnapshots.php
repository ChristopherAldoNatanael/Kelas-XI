<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordCredentialSnapshot;
use App\Models\MedicalRecordVersion;
use App\Services\ProfessionalCredentialService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Versi lama tidak boleh diberi snapshot "valid" dari kredensial hari ini.
 * Snapshot historis yang tidak dapat dibuktikan ditandai `exception`.
 */
class BackfillCredentialSnapshots extends Command
{
    protected $signature = 'medical-records:backfill-credential-snapshots
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah versi per batch}';

    protected $description = 'Tandai versi lama tanpa snapshot kredensial sebagai exception';

    public function handle(): int
    {
        $query = MedicalRecordVersion::query()
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('medical_record_credential_snapshots')
                    ->whereColumn('medical_record_credential_snapshots.version_id', 'medical_record_versions.id');
            })
            ->orderBy('id');

        $total = (clone $query)->count();
        $this->info("Versi tanpa snapshot kredensial: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $created = 0;
        $failed = [];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(max(20, (int) $this->option('chunk')), function ($versions) use (&$created, &$failed, $bar) {
            foreach ($versions as $version) {
                try {
                    $record = MedicalRecord::with(['doctor', 'clinic'])->find($version->medical_record_id);

                    if (! $record) {
                        $failed[] = ['version_id' => $version->id, 'message' => 'record tidak ditemukan'];
                        $bar->advance();
                        continue;
                    }

                    $rows = [
                        [
                            'holder_type' => ProfessionalCredentialService::HOLDER_DOCTOR,
                            'holder_id' => $record->doctor_id,
                            'credential_type' => 'STR',
                            'number' => $record->doctor?->str_number,
                            'expires_at' => $record->doctor?->str_expiry,
                        ],
                        [
                            'holder_type' => ProfessionalCredentialService::HOLDER_DOCTOR,
                            'holder_id' => $record->doctor_id,
                            'credential_type' => 'SIP',
                            'number' => $record->doctor?->sip_number,
                            'expires_at' => $record->doctor?->sip_expiry,
                        ],
                        [
                            'holder_type' => ProfessionalCredentialService::HOLDER_CLINIC,
                            'holder_id' => $record->clinic_id,
                            'credential_type' => 'CLINIC_LICENSE',
                            'number' => $record->clinic?->license_number,
                            'expires_at' => $record->clinic?->license_expiry,
                        ],
                    ];

                    foreach ($rows as $row) {
                        if ($row['holder_id'] === null) {
                            continue;
                        }

                        MedicalRecordCredentialSnapshot::firstOrCreate(
                            [
                                'version_id' => $version->id,
                                'holder_type' => $row['holder_type'],
                                'holder_id' => $row['holder_id'],
                                'credential_type' => $row['credential_type'],
                            ],
                            [
                                'clinic_id' => $record->clinic_id,
                                'medical_record_id' => $record->id,
                                'number' => $row['number'],
                                'expires_at' => $row['expires_at'],
                                'practice_scope' => $row['holder_type'] === ProfessionalCredentialService::HOLDER_CLINIC
                                    ? $record->clinic?->name
                                    : null,
                                'credential_status' => ProfessionalCredentialService::SNAPSHOT_EXCEPTION,
                                'verified_at' => null,
                                'verified_by' => null,
                                'snapshot' => [
                                    'legacy' => true,
                                    'reason' => 'no_snapshot_at_finalization',
                                ],
                                'created_at' => now(),
                            ]
                        );

                        $created++;
                    }

                    $bar->advance();
                } catch (Throwable $e) {
                    $failed[] = ['version_id' => $version->id, 'message' => $e->getMessage()];
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Snapshot dibuat: {$created}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['version_id', 'message'], array_slice($failed, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
