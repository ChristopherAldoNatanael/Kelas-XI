<?php

namespace App\Console\Commands;

use App\Models\MedicalRecordVitals;
use App\Models\VitalMeasurement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Salin snapshot vital lama ke riwayat `vital_measurements` sekali saja.
 * Idempotent melalui `legacy_vital_id` unik.
 */
class BackfillVitalMeasurements extends Command
{
    protected $signature = 'medical-records:backfill-vitals
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah baris per batch}';

    protected $description = 'Backfill riwayat pengukuran vital dari tabel legacy';

    public function handle(): int
    {
        $query = MedicalRecordVitals::query()
            ->whereNotIn('id', VitalMeasurement::whereNotNull('legacy_vital_id')->select('legacy_vital_id'));

        $total = (clone $query)->count();
        $this->info("Snapshot vital yang belum memiliki riwayat: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $created = 0;
        $failed = [];

        $query->orderBy('id')->chunkById((int) $this->option('chunk'), function ($rows) use (&$created, &$failed) {
            foreach ($rows as $legacy) {
                try {
                    DB::transaction(function () use ($legacy, &$created) {
                        $record = DB::table('medical_records')
                            ->where('id', $legacy->medical_record_id)
                            ->first();

                        if (! $record) {
                            throw new \RuntimeException('Rekam medis tidak ditemukan.');
                        }

                        VitalMeasurement::create([
                            'clinic_id' => $record->clinic_id,
                            'encounter_id' => $record->encounter_id,
                            'medical_record_id' => $record->id,
                            'pet_id' => $record->pet_id,
                            'legacy_vital_id' => $legacy->id,
                            'temperature_c' => $legacy->temperature_c,
                            'heart_rate' => $legacy->heart_rate,
                            'respiratory_rate' => $legacy->respiratory_rate,
                            'crt_seconds' => $legacy->crt_seconds,
                            'mucosa' => $legacy->mucosa,
                            'hydration' => $legacy->hydration,
                            'bcs' => $legacy->bcs,
                            'pain_score' => $legacy->pain_score,
                            'weight_kg' => $legacy->weight_kg,
                            'notes' => $legacy->notes,
                            'measured_at' => $legacy->measured_at ?: $legacy->created_at ?: now(),
                            'source' => VitalMeasurement::SOURCE_LEGACY,
                            'shift' => null,
                            'status' => VitalMeasurement::STATUS_RECORDED,
                            'recorded_by' => null,
                        ]);
                        $created++;
                    });
                } catch (\Throwable $e) {
                    $failed[] = ['vital_id' => $legacy->id, 'message' => $e->getMessage()];
                }
            }
        });

        $this->info("Riwayat vital dibuat: {$created}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['vital_id', 'message'], array_slice($failed, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
