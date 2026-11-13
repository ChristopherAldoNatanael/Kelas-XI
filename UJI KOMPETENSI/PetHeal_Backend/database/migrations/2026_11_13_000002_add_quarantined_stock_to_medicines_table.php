<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo stok karantina di level obat (agregat dari batch).
 *
 * `stock` tetap berarti stok TERSEDIA. `quarantined_stock` adalah stok yang
 * sudah dipisahkan secara fisik dan tidak boleh dipakai untuk resep, tetapi
 * belum keluar permanen dari klinik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->decimal('quarantined_stock', 12, 2)->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('quarantined_stock');
        });
    }
};
