<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail alamat untuk pencarian klinik manual (tanpa izin lokasi).
 *
 * latitude/longitude sudah ada sejak fitur discovery; kolom ini melengkapi
 * wilayah supaya pelanggan bisa memilih kota/kecamatan tanpa GPS. Semua
 * nullable: klinik lama tetap valid dan hanya bisa ditemukan lewat pencarian
 * teks bila koordinatnya belum diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            if (!Schema::hasColumn('clinics', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('clinics', 'district')) {
                $table->string('district', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('clinics', 'postal_code')) {
                $table->string('postal_code', 12)->nullable()->after('district');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['city', 'district', 'postal_code']);
        });
    }
};
