<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 (Operational Trust & Proof): pengecualian jadwal per tanggal.
 *
 * Satu dokter hanya punya satu pengecualian per tanggal (unique doctor_id+date)
 * supaya admin tidak bisa menyimpan "cuti" dan "jam khusus" sekaligus untuk
 * hari yang sama. `is_closed = true` berarti cuti sehari penuh; `is_closed =
 * false` berarti jam praktik diganti untuk tanggal itu saja.
 *
 * Kolom lama (available_days/start_time/end_time) TIDAK diubah: pengecualian
 * bersifat aditif sehingga jadwal mingguan tetap menjadi default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['doctor_id', 'date']);
            $table->index(['clinic_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_exceptions');
    }
};
