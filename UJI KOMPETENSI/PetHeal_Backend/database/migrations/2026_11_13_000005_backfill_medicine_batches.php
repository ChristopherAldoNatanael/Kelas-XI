<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill batch untuk stok yang sudah ada sebelum fitur batch.
 *
 * Setiap obat yang masih punya stok mendapat satu batch "AWAL" dengan tanggal
 * kedaluwarsa yang selama ini dipakai di level obat. Dengan begitu:
 *  - saldo obat lama tetap sama (tidak ada stok yang hilang/bertambah),
 *  - monitoring per batch langsung berfungsi untuk data existing,
 *  - tidak perlu migrasi destruktif atau reset data demo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('medicines')
            ->select(['id', 'clinic_id', 'stock', 'expiry_date'])
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->chunk(200, function ($medicines) {
                $now = now();
                $rows = [];

                foreach ($medicines as $medicine) {
                    $rows[] = [
                        'clinic_id' => $medicine->clinic_id,
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'AWAL',
                        'expiry_date' => $medicine->expiry_date,
                        'stock' => $medicine->stock,
                        'quarantined_stock' => 0,
                        'received_at' => null,
                        'notes' => 'Batch awal dari data sebelum pencatatan batch.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('medicine_batches')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('medicine_batches')->where('batch_number', 'AWAL')->delete();
    }
};
