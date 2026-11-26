<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail resep yang dibutuhkan untuk penyerahan obat & audit farmasi.
 *
 * `batch_number`/`expiry_date` diisi dari batch yang benar-benar dipotong
 * (FEFO), bukan dari input form: itulah yang tercetak pada label dan dipakai
 * saat penarikan obat (recall). `drug_class` mengikuti klasifikasi obat hewan
 * (bebas, bebas terbatas, keras, narkotika) agar resep obat keras bisa
 * dilaporkan terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('drug_class', 30)->default('bebas_terbatas')->after('form');
            $table->boolean('is_antibiotic')->default(false)->after('drug_class');
            $table->index(['clinic_id', 'drug_class'], 'medicines_clinic_drug_class_index');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('batch_number', 80)->nullable()->after('unit');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->string('route', 50)->nullable()->after('dose');         // oral, topikal, injeksi
            $table->string('frequency', 50)->nullable()->after('route');    // 2x sehari
            $table->unsignedSmallInteger('duration_days')->nullable()->after('frequency');
            $table->string('instruction', 255)->nullable()->after('duration_days');
            $table->string('drug_class', 30)->nullable()->after('instruction');
            $table->boolean('is_antibiotic')->default(false)->after('drug_class');
            $table->timestamp('dispensed_at')->nullable()->after('is_antibiotic');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn([
                'batch_number', 'expiry_date', 'route', 'frequency', 'duration_days',
                'instruction', 'drug_class', 'is_antibiotic', 'dispensed_at',
            ]);
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('medicines_clinic_drug_class_index');
            $table->dropColumn(['drug_class', 'is_antibiotic']);
        });
    }
};
