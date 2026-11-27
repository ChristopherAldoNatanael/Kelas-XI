<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ClinicalEncounter;
use App\Models\MedicalRecord;
use App\Services\RecordLinkService;
use Illuminate\Console\Command;
use Throwable;

/**
 * M2 — backfill encounter dari rekam medis lama.
 *
 * Command bersifat idempoten. Konflik tidak ditebak: baris yang gagal dicatat
 * dan harus diselesaikan lewat manual review dengan daftar ID eksplisit.
 */
class BackfillClinicalEncounters extends Command
{
    protected $signature = 'medical-records:backfill-encounters
        {--dry-run : Hitung tanpa menulis}
        {--chunk=500 : Jumlah baris per batch}';

    protected $description = 'Buat clinical_encounters dan tautkan rekam medis lama secara idempoten';

    public function handle(RecordLinkService $links): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(50, (int) $this->option('chunk'));

        $base = MedicalRecord::query()->whereNull('encounter_id')->orderBy('id');
        $total = (clone $base)->count();

        $this->info(($dryRun ? '[dry-run] ' : '') . "Rekam medis tanpa encounter: {$total}");

        if ($dryRun || $total === 0) {
            return self::SUCCESS;
        }

        $linked = 0;
        $conflicts = [];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $base->chunkById($chunk, function ($records) use ($links, &$linked, &$conflicts, $bar) {
            foreach ($records as $record) {
                try {
                    if ($record->booking_id !== null) {
                        $booking = Booking::find($record->booking_id);

                        if (! $booking) {
                            throw new \RuntimeException(
                                "Booking #{$record->booking_id} tidak ditemukan untuk rekam medis #{$record->id}."
                            );
                        }

                        $links->linkBooking($record, $booking);
                    } else {
                        $links->openWithoutBooking(
                            $record,
                            ClinicalEncounter::TYPE_WALK_IN,
                            ['chief_complaint' => $record->subjective]
                        );
                    }

                    $linked++;
                } catch (Throwable $e) {
                    $conflicts[] = [
                        'record_id' => $record->id,
                        'booking_id' => $record->booking_id,
                        'message' => $e->getMessage(),
                    ];
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Berhasil ditautkan: {$linked}");

        if ($conflicts !== []) {
            $this->error('Konflik: ' . count($conflicts));
            $this->table(
                ['record_id', 'booking_id', 'message'],
                array_map(fn ($row) => [$row['record_id'], $row['booking_id'], $row['message']], $conflicts)
            );

            return self::FAILURE;
        }

        $remaining = MedicalRecord::whereNull('encounter_id')->count();
        if ($remaining > 0) {
            $this->error("Masih ada {$remaining} rekam medis tanpa encounter.");

            return self::FAILURE;
        }

        $this->info('Semua rekam medis sudah memiliki encounter.');

        return self::SUCCESS;
    }
}
