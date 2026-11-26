<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas legal tenaga & fasilitas.
 *
 * Nomor STR/SIP dokter hewan dan izin klinik hewan adalah identitas yang
 * wajib tercetak pada dokumen medis, sekaligus dasar kewenangan praktik.
 * Masa berlaku disimpan agar akun dokter bisa ditandai kedaluwarsa, bukan
 * baru ketahuan saat audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('str_number', 50)->nullable()->after('specialization');
            $table->date('str_expiry')->nullable()->after('str_number');
            $table->string('sip_number', 50)->nullable()->after('str_expiry');
            $table->date('sip_expiry')->nullable()->after('sip_number');
            $table->string('signature_path')->nullable()->after('photo');
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->string('license_number', 80)->nullable()->after('slug');
            $table->date('license_expiry')->nullable()->after('license_number');
            $table->foreignId('responsible_doctor_id')->nullable()->after('license_expiry')
                ->constrained('doctors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_doctor_id');
            $table->dropColumn(['license_number', 'license_expiry']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn(['str_number', 'str_expiry', 'sip_number', 'sip_expiry', 'signature_path']);
        });
    }
};
