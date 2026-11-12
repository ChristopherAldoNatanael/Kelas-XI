<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran hasil pemeriksaan (foto rontgen, hasil laboratorium, foto kondisi
 * hewan). Disimpan sebagai file di disk `public` dengan path relatif — pola
 * yang sama dengan foto hewan dan foto dokter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->onDelete('cascade');
            // Path relatif di disk public, contoh: medical-records/12/lab.jpg
            $table->string('path', 512);
            // Nama asli berkas supaya dokter/admin mengenali dokumennya.
            $table->string('original_name', 255)->nullable();
            // Keterangan singkat, mis. "Rontgen dada lateral".
            $table->string('caption', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_attachments');
    }
};
