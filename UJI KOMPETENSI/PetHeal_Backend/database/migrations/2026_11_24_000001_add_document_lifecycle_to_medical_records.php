<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siklus hidup dokumen rekam medis.
 *
 * Rekam medis bukan lagi baris tabel yang bisa dihapus: ia punya status
 * (final/void/archived), penulis, waktu finalisasi, alasan pembatalan, dan
 * cap integritas. `booking_id` dibuat unik karena satu kunjungan hanya boleh
 * menghasilkan satu rekam medis — penjagaan di controller saja tidak cukup
 * saat dua request datang bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Rekaman lama sudah dipakai sebagai dokumen sah, jadi default-nya
            // final — bukan draft — agar tidak ada data lama yang tiba-tiba
            // dianggap belum disahkan.
            $table->string('status', 16)->default('final')->after('clinic_id');
            $table->foreignId('created_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('updated_by');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('finalized_by');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable()->after('voided_by');
            $table->char('content_hash', 64)->nullable()->after('void_reason');
            $table->timestamp('archived_at')->nullable()->after('content_hash');

            // Outcome klinis — dibutuhkan untuk statistik klinik, kesejahteraan
            // hewan, dan surat keterangan.
            $table->string('outcome', 20)->nullable()->after('archived_at');
            $table->string('cause_of_death', 255)->nullable()->after('outcome');
            $table->string('euthanasia_reason', 255)->nullable()->after('cause_of_death');
            $table->string('disposal_method', 50)->nullable()->after('euthanasia_reason');

            $table->unique('booking_id', 'medical_records_booking_id_unique');
            $table->index(['pet_id', 'created_at'], 'medical_records_pet_created_index');
            $table->index(['status', 'created_at'], 'medical_records_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropUnique('medical_records_booking_id_unique');
            $table->dropIndex('medical_records_pet_created_index');
            $table->dropIndex('medical_records_status_created_index');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn([
                'status', 'finalized_at', 'voided_at', 'void_reason', 'content_hash',
                'archived_at', 'outcome', 'cause_of_death', 'euthanasia_reason', 'disposal_method',
            ]);
        });
    }
};
