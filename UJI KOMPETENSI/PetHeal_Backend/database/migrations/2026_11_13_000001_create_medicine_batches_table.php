<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch obat per klinik.
 *
 * Kenapa batch dipisah dari `medicines`:
 *  - Satu obat bisa datang dari beberapa penerimaan dengan tanggal kedaluwarsa
 *    berbeda. Monitoring "hampir ED" harus per batch, bukan per nama obat.
 *  - Karantina memindahkan stok dari available ke quarantined TANPA mengubah
 *    total fisik, sehingga dua saldo perlu disimpan per batch.
 *
 * `medicines.stock` tetap dipertahankan sebagai total available (kompatibilitas
 * daftar obat, resep, API lama), sedangkan `medicine_batches.stock` adalah
 * rinciannya. Keduanya dijaga konsisten oleh MedicineStockService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('cascade');
            $table->string('batch_number', 80);
            $table->date('expiry_date')->nullable();
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('quarantined_stock', 12, 2)->default(0);
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Nomor batch unik per obat (bukan global): dua obat berbeda boleh
            // memakai nomor batch yang sama dari pabrik yang sama.
            $table->unique(['medicine_id', 'batch_number']);
            $table->index(['clinic_id', 'expiry_date']);
            $table->index(['medicine_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
    }
};
