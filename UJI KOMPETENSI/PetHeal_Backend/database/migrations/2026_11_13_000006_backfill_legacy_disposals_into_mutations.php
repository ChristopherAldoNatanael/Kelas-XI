<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyatukan catatan pemusnahan lama ke riwayat mutasi stok.
 *
 * Sebelum ada transaksi mutasi, pemusnahan hanya menyimpan `disposed_*` di
 * tabel medicines dan satu baris buku besar. Supaya riwayat tidak terpecah
 * antara "halaman berita acara" dan "halaman mutasi", catatan lama dibuatkan
 * dokumen transaksi `completed` yang menunjuk ke mutasi buku besarnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $query = DB::table('medicines')
            ->whereNotNull('disposed_at')
            ->whereNotNull('disposal_method')
            ->where('disposed_quantity', '>', 0)
            ->orderBy('id');

        if (Schema::hasColumn('medicines', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $query->chunkById(100, function ($medicines) {
            foreach ($medicines as $medicine) {
                $reference = 'MUT-LEGACY-' . $medicine->id;

                if (DB::table('medicine_stock_transactions')->where('reference', $reference)->exists()) {
                    continue;
                }

                // Jangan gandakan catatan bila obat ini sudah punya transaksi
                // pemusnahan/retur dari alur baru.
                $alreadyRecorded = DB::table('medicine_stock_transactions')
                    ->where('medicine_id', $medicine->id)
                    ->whereIn('type', ['disposal', 'return_supplier'])
                    ->where('status', 'completed')
                    ->exists();

                if ($alreadyRecorded) {
                    continue;
                }

                $isReturn = $medicine->disposal_method === 'returned_supplier';
                $batchId = DB::table('medicine_batches')
                    ->where('medicine_id', $medicine->id)
                    ->orderBy('id')
                    ->value('id');
                $date = substr((string) $medicine->disposed_at, 0, 10);

                $transactionId = DB::table('medicine_stock_transactions')->insertGetId([
                    'reference' => $reference,
                    'clinic_id' => $medicine->clinic_id,
                    'medicine_id' => $medicine->id,
                    'batch_id' => $batchId,
                    'type' => $isReturn ? 'return_supplier' : 'disposal',
                    'status' => 'completed',
                    'quantity' => $medicine->disposed_quantity,
                    'reason' => $medicine->disposal_reason ?: 'Pemusnahan stok lama',
                    'return_date' => $isReturn ? $date : null,
                    'destruction_date' => $isReturn ? null : $date,
                    'destruction_method' => $isReturn ? null : 'other',
                    'from_quarantine' => false,
                    'notes' => 'Dimigrasikan otomatis dari catatan pemusnahan lama.',
                    'completed_at' => $medicine->disposed_at,
                    'created_at' => $medicine->disposed_at,
                    'updated_at' => $medicine->disposed_at,
                ]);

                // Hubungkan mutasi buku besar lama agar detail transaksi tetap
                // menampilkan pergerakan saldo yang sebenarnya.
                $movementId = DB::table('medicine_stock_movements')
                    ->where('medicine_id', $medicine->id)
                    ->where('type', 'disposal')
                    ->whereNull('transaction_id')
                    ->orderByDesc('id')
                    ->value('id');

                if ($movementId) {
                    DB::table('medicine_stock_movements')
                        ->where('id', $movementId)
                        ->update(['transaction_id' => $transactionId]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('medicine_stock_transactions')
            ->where('reference', 'like', 'MUT-LEGACY-%')
            ->delete();
    }
};
