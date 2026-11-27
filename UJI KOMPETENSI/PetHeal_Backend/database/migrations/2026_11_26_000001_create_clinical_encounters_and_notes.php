<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0.2 — encounter sebagai sumber kebenaran relasi booking.
 *
 * Perubahan ini additive:
 * - tabel encounter dan catatan klinis baru;
 * - `medical_records.encounter_id` nullable dan unik;
 * - `medical_records.booking_id` menjadi nullable untuk walk-in/emergency.
 *
 * Tidak ada data lama yang dihapus. Backfill dijalankan oleh command
 * `medical-records:backfill-encounters`, bukan di dalam migrasi, supaya
 * kegagalan data bisa diperiksa tanpa memblokir deploy skema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->unique()->constrained('bookings')->nullOnDelete();
            $table->string('type', 20)->default('booking');
            $table->string('status', 20)->default('planned');
            $table->foreignId('attending_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('supervising_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->string('triage_level', 20)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('disposition', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'pet_id']);
            $table->index(['clinic_id', 'opened_at']);
        });

        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('clinical_encounters')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->string('note_type', 30)->default('progress');
            $table->string('status', 20)->default('draft');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->foreignId('correction_of_id')->nullable()->constrained('clinical_notes')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'encounter_id', 'recorded_at']);
            $table->index(['medical_record_id', 'status']);
            $table->index(['correction_of_id']);
        });

        Schema::table('medical_records', function (Blueprint $table) {
            // Nullable agar walk-in/emergency tidak dipaksa membuat booking
            // palsu. Unique agar satu encounter hanya punya satu rekam medis.
            $table->foreignId('encounter_id')->nullable()->after('booking_id')
                ->unique()->constrained('clinical_encounters')->nullOnDelete();
        });

        // MySQL mempertahankan index dan foreign key saat kolom diubah.
        Schema::table('medical_records', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropUnique(['encounter_id']);
            $table->dropConstrainedForeignId('encounter_id');
        });

        // Kembalikan NOT NULL hanya bila semua baris punya booking. Bila ada
        // walk-in, rollback sengaja gagal daripada menghapus data pasien.
        if (! Schema::hasTable('medical_records')) {
            return;
        }

        $hasWalkIn = \Illuminate\Support\Facades\DB::table('medical_records')
            ->whereNull('booking_id')
            ->exists();

        if (! $hasWalkIn) {
            Schema::table('medical_records', function (Blueprint $table) {
                $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('clinical_notes');
        Schema::dropIfExists('clinical_encounters');
    }
};
