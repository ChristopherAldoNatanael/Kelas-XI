<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Arsipkan rekam medis yang sudah melewati masa retensi.
 *
 * Rekam medis tidak pernah dihapus. Setelah 5 tahun (default) rekaman
 * dipindahkan ke status `archived` agar tidak muncul di daftar harian, tetapi
 * tetap utuh untuk pemeriksaan, ekspor, dan pembelaan hukum. Rekaman dengan
 * tunggakan pembayaran ditahan sampai 10 tahun sesuai retensi keuangan.
 */
class ArchiveMedicalRecords extends Command
{
    protected $signature = 'medical-records:archive
                            {--dry-run : Tampilkan jumlah tanpa mengubah data}';

    protected $description = 'Arsipkan rekam medis yang melewati masa retensi (tidak menghapus data)';

    public function handle(): int
    {
        $clinicalYears = max(1, (int) config('medical_records.retention_years', 5));
        $financialYears = max($clinicalYears, (int) config('medical_records.financial_retention_years', 10));

        $clinicalCutoff = now()->subYears($clinicalYears);
        $financialCutoff = now()->subYears($financialYears);

        $query = MedicalRecord::query()
            ->where('status', MedicalRecord::STATUS_FINAL)
            ->where(function ($q) use ($clinicalCutoff, $financialCutoff) {
                $q->where(function ($inner) use ($clinicalCutoff) {
                    $inner->whereIn('extra_payment_status', ['not_required', 'paid'])
                        ->where('created_at', '<=', $clinicalCutoff);
                })->orWhere(function ($inner) use ($financialCutoff) {
                    $inner->whereNotIn('extra_payment_status', ['not_required', 'paid'])
                        ->where('created_at', '<=', $financialCutoff);
                });
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Tidak ada rekam medis yang perlu diarsipkan.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$total} rekam medis akan diarsipkan (dry run, tidak ada perubahan).");

            return self::SUCCESS;
        }

        $archived = 0;

        $query->chunkById(200, function ($records) use (&$archived) {
            foreach ($records as $record) {
                try {
                    DB::transaction(function () use ($record) {
                        $record->forceFill([
                            'status' => MedicalRecord::STATUS_ARCHIVED,
                            'archived_at' => now(),
                        ])->save();
                    });

                    $archived++;
                } catch (\Throwable $e) {
                    Log::error('Gagal mengarsipkan rekam medis', [
                        'medical_record_id' => $record->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("{$archived} dari {$total} rekam medis berhasil diarsipkan.");

        return self::SUCCESS;
    }
}
