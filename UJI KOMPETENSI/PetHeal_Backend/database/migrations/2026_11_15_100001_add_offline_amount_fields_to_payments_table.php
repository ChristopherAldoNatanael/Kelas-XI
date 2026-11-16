<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nominal uang diterima & kembalian untuk pembayaran offline (bayar di klinik).
 *
 * `gross_amount` tetap berisi nominal yang benar-benar menjadi pendapatan
 * (total tagihan). Nominal yang diterima petugas dan kembalian dicatat
 * terpisah supaya laporan keuangan tidak ikut menghitung kembalian sebagai
 * pendapatan. Kolom nullable: transaksi online tidak punya kembalian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'amount_received')) {
                $table->unsignedBigInteger('amount_received')->nullable()
                    ->after('gross_amount')
                    ->comment('Nominal uang yang diterima petugas (offline)');
            }

            if (!Schema::hasColumn('payments', 'change_amount')) {
                $table->unsignedBigInteger('change_amount')->nullable()
                    ->after('amount_received')
                    ->comment('Uang kembalian = amount_received - gross_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = array_filter(
                ['amount_received', 'change_amount'],
                fn (string $column) => Schema::hasColumn('payments', $column)
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
