<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pemusnahan obat kedaluwarsa.
 *
 * Menandai label "kedaluwarsa" saja tidak cukup: penguji akan bertanya
 * "lalu apa?". Kolom di sini menyimpan jawabannya — kapan dimusnahkan,
 * berapa banyak, dengan cara apa, dan alasannya. Pemusnahan tetap dicatat
 * sebagai mutasi stok (`type = disposal`) supaya saldo bisa direkonsiliasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->timestamp('disposed_at')->nullable()->after('is_active');
            // destroyed = dibakar/dilarutkan sesuai SOP; returned_supplier = retur ke distributor.
            $table->string('disposal_method', 30)->nullable()->after('disposed_at');
            $table->string('disposal_reason', 255)->nullable()->after('disposal_method');
            $table->decimal('disposed_quantity', 12, 2)->nullable()->after('disposal_reason');

            // Filter operasional: "obat kedaluwarsa" dan "sudah dimusnahkan".
            $table->index(['clinic_id', 'expiry_date'], 'medicines_clinic_expiry_index');
            $table->index(['clinic_id', 'disposed_at'], 'medicines_clinic_disposed_index');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('medicines_clinic_expiry_index');
            $table->dropIndex('medicines_clinic_disposed_index');
            $table->dropColumn([
                'disposed_at',
                'disposal_method',
                'disposal_reason',
                'disposed_quantity',
            ]);
        });
    }
};
