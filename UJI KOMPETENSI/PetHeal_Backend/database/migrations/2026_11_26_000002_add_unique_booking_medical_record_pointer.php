<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M2 — satu booking hanya boleh menunjuk satu rekam medis.
 *
 * Migrasi sengaja gagal bila menemukan duplikat. Duplikat adalah data yang
 * harus direview manusia, bukan dipilih otomatis oleh migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('bookings')
            ->select('medical_record_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('medical_record_id')
            ->groupBy('medical_record_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $ids = $duplicates->pluck('medical_record_id')->implode(', ');
            throw new RuntimeException(
                "Duplikat bookings.medical_record_id ditemukan untuk record: {$ids}. "
                . 'Selesaikan manual review sebelum menjalankan migrasi ini.'
            );
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique('medical_record_id', 'bookings_medical_record_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_medical_record_id_unique');
        });
    }
};
