<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan isi rekam medis.
 *
 * `before`/`after` menyimpan potongan data yang berubah saja (bukan seluruh
 * record) supaya baris tetap ringkas, tetapi cukup untuk menjawab "siapa
 * mengubah apa, kapan, dan atas alasan apa". Resep ikut dicatat di sini
 * sebagai bagian dari `after`, sehingga koreksi resep tidak menghapus bukti
 * obat yang pernah diberikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            // create|update|void|finalize|prescription|attachment
            $table->string('action', 20);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medical_record_id', 'created_at'], 'medical_record_revisions_record_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_revisions');
    }
};
