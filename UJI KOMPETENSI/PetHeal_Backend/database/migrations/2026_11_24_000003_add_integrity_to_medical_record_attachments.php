<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran medis tidak lagi dihapus permanen dan menyimpan cap integritas.
 *
 * Berkas rontgen/lab adalah bukti pemeriksaan; penghapusan barisnya harus
 * terlihat sebagai riwayat, bukan hilang tanpa jejak. `sha256` dipakai untuk
 * memastikan berkas yang ditampilkan sama dengan yang diunggah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_record_attachments', function (Blueprint $table) {
            $table->char('sha256', 64)->nullable()->after('size_bytes');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('medical_record_attachments', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('sha256');
        });
    }
};
