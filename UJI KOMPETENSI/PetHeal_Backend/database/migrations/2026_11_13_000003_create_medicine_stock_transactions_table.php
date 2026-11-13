<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header transaksi mutasi stok.
 *
 * `medicine_stock_movements` adalah buku besar saldo; tabel ini adalah
 * DOKUMEN transaksi yang menjelaskan kenapa saldo bergerak, siapa yang
 * memproses, dan apa tindak lanjutnya. Satu transaksi selesai dapat
 * menghasilkan lebih dari satu baris mutasi (mis. karantina: available -q
 * dan quarantine +q).
 *
 * Stok hanya berubah saat status menjadi `completed`. Dengan begitu:
 *  - transaksi `pending`/`processing` bisa dibatalkan tanpa merusak saldo,
 *  - tidak ada pengurangan dua kali saat tombol diproses berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_stock_transactions', function (Blueprint $table) {
            $table->id();
            // Nomor dokumen yang dibaca petugas, mis. MUT-20261113-0001.
            $table->string('reference', 32)->unique();

            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->foreignId('medicine_id')->constrained('medicines')->onDelete('cascade');
            $table->foreignId('batch_id')->nullable()
                ->constrained('medicine_batches')->nullOnDelete();

            // return_supplier | disposal | quarantine | damaged | adjustment
            $table->string('type', 30);
            // pending | processing | completed | cancelled
            $table->string('status', 20)->default('pending');

            // Selalu positif. Arah keluar/masuk ditentukan oleh jenis transaksi.
            $table->decimal('quantity', 12, 2);
            $table->string('reason', 255);
            $table->string('destination', 100)->nullable();
            $table->text('notes')->nullable();

            // Retur supplier.
            $table->string('supplier_name', 150)->nullable();
            $table->date('return_date')->nullable();
            // Bukti retur / berita acara (path file di storage).
            $table->string('document_path', 255)->nullable();

            // Pemusnahan.
            $table->date('destruction_date')->nullable();
            $table->string('destruction_method', 50)->nullable();
            $table->string('witness_name', 150)->nullable();
            $table->string('minutes_path', 255)->nullable();

            // Karantina.
            $table->string('quarantine_location', 100)->nullable();

            // Stok diambil dari saldo karantina (retur/pemusnahan lanjutan).
            $table->boolean('from_quarantine')->default(false);
            // Transaksi karantina yang menjadi sumber tindak lanjut.
            $table->foreignId('source_transaction_id')->nullable()
                ->constrained('medicine_stock_transactions')->nullOnDelete();

            // Penyesuaian: in | out. Null untuk jenis lain.
            $table->string('direction', 10)->nullable();

            // Audit trail petugas.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'type']);
            $table->index(['medicine_id', 'created_at']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_stock_transactions');
    }
};
