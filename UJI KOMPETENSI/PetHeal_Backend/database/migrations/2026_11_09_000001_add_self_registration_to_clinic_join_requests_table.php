<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran klinik mandiri.
 *
 * Satu tabel untuk dua jenis permintaan: `join` (gabung klinik yang sudah ada,
 * alur lama) dan `new_clinic` (pemilik mendaftarkan klinik baru). Data klinik
 * disimpan sebagai snapshot `requested_*` karena klinik baru belum dibuat
 * sebelum super admin menyetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->string('type', 20)->default('join')->after('id');

            $table->string('requested_clinic_name')->nullable()->after('clinic_id');
            $table->string('requested_clinic_slug')->nullable()->after('requested_clinic_name');
            $table->text('requested_clinic_address')->nullable()->after('requested_clinic_slug');
            $table->string('requested_clinic_phone', 50)->nullable()->after('requested_clinic_address');
            $table->string('requested_clinic_email')->nullable()->after('requested_clinic_phone');
            $table->char('requested_primary_color', 7)->nullable()->after('requested_clinic_email');
            $table->text('requested_description')->nullable()->after('requested_primary_color');

            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index(['type', 'status'], 'clinic_join_requests_type_status_index');
            $table->index('requested_clinic_slug', 'clinic_join_requests_slug_index');
        });

        // Permintaan klinik baru tidak punya klinik tujuan (clinic_id = null).
        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
        });

        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
        });

        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->foreign('clinic_id')->references('id')->on('clinics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Tanpa clinic_id, baris tidak bisa memakai foreign key lama. Hapus
        // permintaan klinik baru lebih dulu supaya rollback tetap bersih.
        DB::table('clinic_join_requests')->whereNull('clinic_id')->delete();

        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
        });

        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
        });

        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
        });

        Schema::table('clinic_join_requests', function (Blueprint $table) {
            $table->dropIndex('clinic_join_requests_type_status_index');
            $table->dropIndex('clinic_join_requests_slug_index');
            $table->dropColumn([
                'type',
                'requested_clinic_name',
                'requested_clinic_slug',
                'requested_clinic_address',
                'requested_clinic_phone',
                'requested_clinic_email',
                'requested_primary_color',
                'requested_description',
                'review_note',
            ]);
        });
    }
};
