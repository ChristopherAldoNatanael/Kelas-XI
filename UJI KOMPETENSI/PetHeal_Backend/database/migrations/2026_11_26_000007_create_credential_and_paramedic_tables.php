<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1.1 — kredensial tenaga, paramedik, otorisasi, dan snapshot per versi.
 *
 * Semua tabel baru dan nullable/additive. Data dokter lama tidak dihapus;
 * backfill dilakukan command terpisah agar kegagalan data tidak memblokir
 * deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paramedics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->foreignId('supervising_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'is_active']);
        });

        Schema::create('professional_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('paramedic_id')->nullable()->constrained('paramedics')->cascadeOnDelete();
            $table->string('credential_type', 20);
            $table->string('number', 80);
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('practice_scope', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_path', 255)->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('active_key', 180)->nullable()->unique();
            $table->timestamp('superseded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'credential_type', 'status']);
            $table->index(['doctor_id', 'credential_type', 'is_current'], 'prof_cred_doctor_type_current_idx');
            $table->index(['paramedic_id', 'credential_type', 'is_current'], 'prof_cred_paramedic_type_current_idx');
        });

        Schema::create('credential_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('holder_type', 20);
            $table->unsignedBigInteger('holder_id');
            $table->string('credential_type', 20);
            $table->string('credential_status', 20);
            $table->text('emergency_context');
            $table->text('legal_basis')->nullable();
            $table->string('external_reviewer', 150)->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['medical_record_id', 'credential_type']);
            $table->index(['holder_type', 'holder_id']);
        });

        Schema::create('paramedic_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('paramedic_id')->constrained('paramedics')->cascadeOnDelete();
            $table->string('action_code', 60);
            $table->string('supervision_mode', 20)->default('none');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('active_key', 160)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'action_code', 'is_active']);
            $table->index(['paramedic_id', 'is_active']);
        });

        Schema::create('medical_record_credential_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('medical_record_versions')->cascadeOnDelete();
            $table->string('holder_type', 20);
            $table->unsignedBigInteger('holder_id');
            $table->string('credential_type', 20);
            $table->string('number', 80)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('practice_scope', 255)->nullable();
            $table->string('credential_status', 20);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['version_id', 'holder_type', 'holder_id', 'credential_type'],
                'credential_snapshots_version_holder_unique'
            );
            $table->index(['medical_record_id', 'credential_status'], 'cred_snapshots_record_status_idx');
            $table->index(['clinic_id', 'created_at'], 'cred_snapshots_clinic_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_credential_snapshots');
        Schema::dropIfExists('paramedic_authorizations');
        Schema::dropIfExists('credential_exceptions');
        Schema::dropIfExists('professional_credentials');
        Schema::dropIfExists('paramedics');
    }
};
