<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOAP (Subjective, Objective, Assessment, Plan) — format catatan medis
 * standar yang dipakai dokter hewan.
 *
 * Kolom lama (`diagnosis`, `treatment`, `notes`) TIDAK dihapus: rekam medis
 * yang sudah ada tetap terbaca, laporan/ekspor lama tidak berubah, dan
 * `diagnosis`/`treatment` tetap wajib di form. Kolom SOAP bersifat tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->text('subjective')->nullable()->after('doctor_id');
            $table->text('objective')->nullable()->after('subjective');
            $table->text('assessment')->nullable()->after('objective');
            $table->text('plan')->nullable()->after('assessment');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['subjective', 'objective', 'assessment', 'plan']);
        });
    }
};
