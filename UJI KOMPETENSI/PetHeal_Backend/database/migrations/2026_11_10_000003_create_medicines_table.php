<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master obat per klinik.
 *
 * Isolasi tenant: setiap baris WAJIB punya `clinic_id` (tidak nullable) supaya
 * stok klinik A tidak pernah tercampur dengan klinik B. Berbeda dengan
 * `services.clinic_id` yang nullable karena warisan data lama.
 *
 * `stock` adalah hasil akhir dari mutasi; sumber kebenarannya tetap
 * `medicine_stock_movements`. Kolom ini disimpan agar daftar obat bisa
 * diurutkan/difilter tanpa agregasi setiap request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            // Kategori bebas (antibiotik, antiparasit, vitamin, ...) — bukan
            // tabel terpisah supaya admin tidak dipaksa membuat kategori dulu.
            $table->string('name', 255);
            // Satuan jual/pakai: tablet, ml, botol, sachet.
            $table->string('unit', 30)->default('tablet');
            $table->string('form', 50)->nullable();       // tablet, sirup, injeksi
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);   // harga per satuan
            $table->date('expiry_date')->nullable();
            $table->string('manufacturer', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Nama obat unik per klinik: mencegah dua baris "Amoxicillin" yang
            // membuat mutasi stok terpecah dan laporan tidak bisa dipercaya.
            $table->unique(['clinic_id', 'name']);
            $table->index(['clinic_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
