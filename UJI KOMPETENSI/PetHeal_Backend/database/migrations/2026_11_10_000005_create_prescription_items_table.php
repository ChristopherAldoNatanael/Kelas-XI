<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item resep terstruktur pada rekam medis.
 *
 * Sebelumnya obat hanya teks bebas di `medical_records.medicine`, sehingga
 * tidak mungkin memotong stok. Tabel ini menghubungkan resep ke obat nyata.
 *
 * Kolom `medicine_name`, `dose`, dan `quantity` disimpan sebagai salinan
 * (snapshot) saat resep ditulis: kalau nama/harga obat di master berubah di
 * kemudian hari, resep lama tetap menampilkan apa yang benar-benar diberikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->onDelete('cascade');
            // Nullable: resep bisa tetap dicatat walau obat tidak ada di master
            // (mis. obat dari luar). Stok tidak bisa dipotong untuk baris ini.
            $table->foreignId('medicine_id')->nullable()
                ->constrained('medicines')->nullOnDelete();
            $table->string('medicine_name', 255);
            $table->string('dose', 100)->nullable();        // "2x1", "1 ml"
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 30)->nullable();
            // Harga satuan saat resep ditulis; dipakai menghitung medicine_cost.
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();

            $table->index('medical_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
