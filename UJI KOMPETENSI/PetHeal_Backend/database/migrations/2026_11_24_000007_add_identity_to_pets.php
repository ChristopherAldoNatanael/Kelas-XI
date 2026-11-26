<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas hewan yang dipakai di rekam medis dan sertifikat.
 *
 * Microchip adalah kunci identitas hewan yang dipakai klinik, asuransi, dan
 * instansi; tanpa itu dua hewan bernama sama tidak bisa dibedakan. `species`
 * lama tetap dipertahankan agar data dan API yang ada tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->string('microchip_number', 30)->nullable()->unique()->after('name');
            $table->string('species_code', 30)->nullable()->after('species');
            $table->string('color', 60)->nullable()->after('breed');
            $table->string('markings', 150)->nullable()->after('color');
            $table->date('neutered_at')->nullable()->after('date_of_birth');
            $table->string('blood_type', 10)->nullable()->after('neutered_at');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropUnique(['microchip_number']);
            $table->dropColumn(['microchip_number', 'species_code', 'color', 'markings', 'neutered_at', 'blood_type']);
        });
    }
};
