<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran medis dapat disimpan terenkripsi di disk.
 *
 * `encryption_version` null berarti berkas lama masih plaintext. Baris dengan
 * nilai 1 memakai AES-256-GCM dengan header PHRM1. Kolom ini additive agar
 * berkas lama tetap dapat diunduh tanpa migrasi mendadak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_record_attachments', function (Blueprint $table) {
            $table->unsignedTinyInteger('encryption_version')
                ->nullable()
                ->after('sha256');
        });
    }

    public function down(): void
    {
        Schema::table('medical_record_attachments', function (Blueprint $table) {
            $table->dropColumn('encryption_version');
        });
    }
};
