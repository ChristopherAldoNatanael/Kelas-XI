<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.3 — indeks untuk daftar rekam medis admin/dokter.
 *
 * Nama indeks dibuat pendek agar tetap di bawah batas 64 karakter MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->index(['clinic_id', 'created_at'], 'idx_mr_clinic_created');
            $table->index(['doctor_id', 'created_at'], 'idx_mr_doctor_created');
            $table->index(['clinic_id', 'status', 'created_at'], 'idx_mr_clinic_status_created');
            $table->index(
                ['clinic_id', 'extra_payment_status', 'created_at'],
                'idx_mr_clinic_payment_created'
            );
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_mr_clinic_created');
            $table->dropIndex('idx_mr_doctor_created');
            $table->dropIndex('idx_mr_clinic_status_created');
            $table->dropIndex('idx_mr_clinic_payment_created');
        });
    }
};
