<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buku besar mutasi stok obat.
 *
 * Setiap perubahan stok WAJIB menghasilkan satu baris di sini. Angka stok di
 * `medicines.stock` adalah saldo hasil penjumlahan mutasi, bukan angka yang
 * bisa diubah diam-diam — sehingga pertanyaan penguji "dari mana angka stok
 * ini?" selalu punya jawaban.
 *
 * `quantity` disimpan bertanda: masuk = positif, keluar = negatif. Dengan
 * begitu saldo = SUM(quantity), dan tidak ada dua konvensi yang bisa salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('cascade');
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            // in | out | adjustment | return
            $table->string('type', 20);
            $table->decimal('quantity', 12, 2);           // bertanda
            $table->decimal('stock_after', 12, 2);        // saldo setelah mutasi
            $table->string('reason', 255)->nullable();
            // Sumber otomatis: rekam medis yang memicu pengurangan.
            $table->foreignId('medical_record_id')->nullable()
                ->constrained('medical_records')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['medicine_id', 'created_at']);
            $table->index(['clinic_id', 'created_at']);
            $table->index('medical_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_stock_movements');
    }
};
