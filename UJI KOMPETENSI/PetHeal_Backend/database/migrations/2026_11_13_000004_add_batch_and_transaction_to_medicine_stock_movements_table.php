<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hubungkan buku besar mutasi ke batch dan transaksi.
 *
 * `stock_state` membedakan perubahan pada saldo TERSEDIA (`available`) dan
 * saldo KARANTINA (`quarantine`), sehingga laporan bisa memisahkan keduanya
 * tanpa menebak dari jenis mutasi.
 *
 * Semua kolom nullable / punya default agar baris mutasi lama tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_stock_movements', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('id')
                ->constrained('medicine_stock_transactions')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('medicine_id')
                ->constrained('medicine_batches')->nullOnDelete();
            $table->string('stock_state', 20)->default('available')->after('type');

            $table->index(['transaction_id']);
            $table->index(['batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('medicine_stock_movements', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropForeign(['batch_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['batch_id', 'created_at']);
            $table->dropColumn(['transaction_id', 'batch_id', 'stock_state']);
        });
    }
};
