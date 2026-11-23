<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bukti dan jejak pengembalian dana.
 *
 * Kenapa dua kolom ini dibutuhkan:
 *
 *  - `proof_path` — klinik kadang harus mentransfer balik ke rekening pasien
 *    yang belum pernah datang ke klinik (booking dibatalkan sebelum kunjungan).
 *    Tanpa bukti, tidak ada cara membuktikan transfer itu benar-benar terjadi
 *    saat pasien mengklaim dana belum masuk. Opsional: transfer tunai di kasir
 *    tidak menghasilkan berkas.
 *
 *  - `provider_refund_id` — bila dana dikembalikan otomatis lewat Midtrans
 *    (kembali ke metode pembayaran asal), nomor refund dari provider disimpan
 *    agar bisa dicocokkan dengan mutasi/notifikasi Midtrans.
 *
 * Metode `midtrans` ditambahkan ke `booking_refunds.method` (kolom string, bukan
 * enum) sehingga tidak ada perubahan tipe di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_refunds', function (Blueprint $table) {
            $table->string('provider_refund_id', 100)->nullable()->after('reference');
            $table->string('proof_path', 255)->nullable()->after('provider_refund_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_refunds', function (Blueprint $table) {
            $table->dropColumn(['provider_refund_id', 'proof_path']);
        });
    }
};
