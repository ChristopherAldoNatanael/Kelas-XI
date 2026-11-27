<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Buat header resep untuk item lama yang belum memiliki prescription_id.
 * Stok item lama sudah dipotong pada saat resep disimpan, sehingga header
 * ditandai `dispensed` sebagai proyeksi historis, bukan penyerahan baru.
 */
class BackfillPrescriptions extends Command
{
    protected $signature = 'pharmacy:backfill-prescriptions
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah record per batch}';

    protected $description = 'Backfill header prescriptions untuk item resep lama';

    public function handle(): int
    {
        $recordIds = PrescriptionItem::whereNull('prescription_id')
            ->distinct()
            ->orderBy('medical_record_id')
            ->pluck('medical_record_id');

        $this->info("Rekam medis dengan item resep tanpa header: {$recordIds->count()}");

        if ($this->option('dry-run') || $recordIds->isEmpty()) {
            return self::SUCCESS;
        }

        $created = 0;
        $attached = 0;
        $failed = [];

        foreach ($recordIds as $recordId) {
            try {
                DB::transaction(function () use ($recordId, &$created, &$attached) {
                    $record = MedicalRecord::whereKey($recordId)->lockForUpdate()->firstOrFail();

                    $prescription = Prescription::where('medical_record_id', $record->id)->first();

                    if (! $prescription) {
                        do {
                            $number = sprintf('RX-LEGACY-%d-%s', $record->id, Str::upper(Str::random(6)));
                        } while (Prescription::where('clinic_id', $record->clinic_id)
                            ->where('prescription_number', $number)->exists());

                        $prescription = Prescription::create([
                            'clinic_id' => $record->clinic_id,
                            'medical_record_id' => $record->id,
                            'prescription_number' => $number,
                            'prescribed_by' => $record->created_by,
                            'prescribed_at' => $record->created_at ?: now(),
                            'status' => Prescription::STATUS_DISPENSED,
                            'notes' => 'Backfill header untuk item resep legacy.',
                        ]);
                        $created++;
                    }

                    $attached += PrescriptionItem::where('medical_record_id', $record->id)
                        ->whereNull('prescription_id')
                        ->update(['prescription_id' => $prescription->id]);
                });
            } catch (\Throwable $e) {
                $failed[] = ['record_id' => $recordId, 'message' => $e->getMessage()];
            }
        }

        $this->info("Header dibuat: {$created}; item ditautkan: {$attached}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['record_id', 'message'], array_slice($failed, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
