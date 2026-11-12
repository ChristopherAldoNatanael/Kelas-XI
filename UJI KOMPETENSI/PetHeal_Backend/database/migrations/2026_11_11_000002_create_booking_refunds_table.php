<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengembalian uang (refund) yang dicatat admin.
 *
 * Mengapa refund terpisah dari `bookings`:
 *  - Satu booking bisa dikembalikan bertahap (mis. dua kali transfer balik),
 *    jadi tidak cukup satu kolom `refunded_at`.
 *  - Refund adalah kejadian keuangan yang harus punya jejak: siapa, kapan,
 *    berapa, lewat cara apa, dan alasannya.
 *
 * PENTING: sistem ini TIDAK mengirim uang. Admin mengembalikan uang lewat
 * bank/e-wallet, lalu mencatatnya di sini sebagai bukti dan agar laporan
 * tidak menghitung uang yang sudah keluar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            // transfer | cash | ewallet | other
            $table->string('method', 30)->default('transfer');
            $table->string('reference', 100)->nullable();   // no. transaksi bank
            $table->text('reason');
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_refunds');
    }
};
