<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kolom operasional baru pada booking:
 *
 *  - `medical_record_id` menghubungkan booking kontrol dengan rekam medis
 *    yang merekomendasikannya. Nullable karena booking biasa tidak punya
 *    rujukan rekam medis. Bukan kolom medis — hanya tautan administratif.
 *  - `queue_note` menyimpan alasan pasien dilewati / tidak datang. Nullable
 *    dan hanya diisi saat status antrian berganti ke no_show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('medical_record_id')
                ->nullable()
                ->after('service_id')
                ->constrained('medical_records')
                ->nullOnDelete();

            $table->string('queue_note', 255)->nullable()->after('queue_finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['medical_record_id']);
            $table->dropColumn(['medical_record_id', 'queue_note']);
        });
    }
};
