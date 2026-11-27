<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * P0.2 — pemeriksa drift relasi booking/encounter/rekam medis.
 *
 * Command ini READ-ONLY. Tidak ada auto-fix, tidak ada delete, dan tidak ada
 * penebakan record mana yang benar. Perbaikan dilakukan lewat manual review
 * dengan daftar ID eksplisit.
 */
class CheckMedicalRecordLinks extends Command
{
    protected $signature = 'medical-records:check-links
        {--clinic= : Batasi ke satu clinic_id}
        {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Laporkan drift relasi booking, encounter, dan rekam medis tanpa mengubah data';

    public function handle(): int
    {
        $clinicId = $this->option('clinic');
        $clinicId = $clinicId !== null ? (int) $clinicId : null;

        $issues = [
            'record_without_encounter' => $this->recordWithoutEncounter($clinicId),
            'encounter_without_record' => $this->encounterWithoutRecord($clinicId),
            'booking_relation_mismatch' => $this->bookingRelationMismatch($clinicId),
            'booking_pointer_missing' => $this->bookingPointerMissing($clinicId),
            'duplicate_booking_pointer' => $this->duplicateBookingPointer($clinicId),
        ];

        $total = array_sum(array_map('count', $issues));

        if ($this->option('json')) {
            $this->line(json_encode([
                'checked_at_utc' => now()->utc()->toIso8601String(),
                'clinic_id' => $clinicId,
                'total_issues' => $total,
                'issues' => $issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $total === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Pemeriksaan relasi rekam medis — clinic: " . ($clinicId ?? 'semua'));
        $this->newLine();

        foreach ($issues as $name => $rows) {
            if ($rows === []) {
                $this->line("  OK  {$name}");
                continue;
            }

            $this->error('  DRIFT ' . $name . ' (' . count($rows) . ')');
            $this->table(array_keys((array) $rows[0]), array_map(
                fn ($row) => array_values((array) $row),
                $rows
            ));
        }

        $this->newLine();
        $this->line("Total temuan: {$total}");

        if ($total > 0) {
            $this->warn('Tidak ada perubahan otomatis. Buat tiket manual review dengan daftar record ID di atas.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function baseRecords(?int $clinicId)
    {
        $query = DB::table('medical_records as mr')
            ->leftJoin('clinical_encounters as ce', 'mr.encounter_id', '=', 'ce.id');

        if ($clinicId !== null) {
            $query->where('mr.clinic_id', $clinicId);
        }

        return $query;
    }

    private function recordWithoutEncounter(?int $clinicId): array
    {
        return $this->baseRecords($clinicId)
            ->whereNull('mr.encounter_id')
            ->select('mr.id as record_id', 'mr.booking_id', 'mr.clinic_id')
            ->limit(200)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function encounterWithoutRecord(?int $clinicId): array
    {
        $query = DB::table('clinical_encounters as ce')
            ->leftJoin('medical_records as mr', 'mr.encounter_id', '=', 'ce.id')
            ->whereNull('mr.id');

        if ($clinicId !== null) {
            $query->where('ce.clinic_id', $clinicId);
        }

        return $query->select('ce.id as encounter_id', 'ce.booking_id', 'ce.clinic_id')
            ->limit(200)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function bookingRelationMismatch(?int $clinicId): array
    {
        $query = DB::table('medical_records as mr')
            ->join('clinical_encounters as ce', 'mr.encounter_id', '=', 'ce.id')
            ->whereRaw('NOT (mr.booking_id <=> ce.booking_id)');

        if ($clinicId !== null) {
            $query->where('mr.clinic_id', $clinicId);
        }

        return $query->select(
            'mr.id as record_id',
            'mr.booking_id as record_booking_id',
            'ce.id as encounter_id',
            'ce.booking_id as encounter_booking_id'
        )->limit(200)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function bookingPointerMissing(?int $clinicId): array
    {
        $query = DB::table('medical_records as mr')
            ->join('bookings as b', 'mr.booking_id', '=', 'b.id')
            ->where(function ($q) {
                $q->whereNull('b.medical_record_id')
                    ->orWhereColumn('b.medical_record_id', '!=', 'mr.id');
            });

        if ($clinicId !== null) {
            $query->where('mr.clinic_id', $clinicId);
        }

        return $query->select(
            'mr.id as record_id',
            'mr.booking_id',
            'b.medical_record_id as booking_pointer'
        )->limit(200)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function duplicateBookingPointer(?int $clinicId): array
    {
        $query = DB::table('bookings as b')
            ->join('medical_records as mr', 'b.medical_record_id', '=', 'mr.id')
            ->select('b.medical_record_id', DB::raw('COUNT(*) as total'))
            ->groupBy('b.medical_record_id')
            ->havingRaw('COUNT(*) > 1');

        if ($clinicId !== null) {
            $query->where('b.clinic_id', $clinicId);
        }

        return $query->limit(200)->get()->map(fn ($row) => (array) $row)->all();
    }
}
