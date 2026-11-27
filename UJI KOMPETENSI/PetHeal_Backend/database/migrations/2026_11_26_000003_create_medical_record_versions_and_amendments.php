<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 — versi immutable dan amendment.
 *
 * Baris versi tidak punya status lifecycle sendiri. Status hanya ada di
 * `medical_records`; versi adalah snapshot historis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->json('snapshot');
            $table->char('content_hash', 64);
            $table->char('legacy_content_hash', 64)->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('amendment_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['medical_record_id', 'version_no'], 'medical_record_versions_record_no_unique');
            $table->index(['clinic_id', 'finalized_at']);
            $table->index(['amendment_id']);
        });

        Schema::create('medical_record_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('base_version_id')->constrained('medical_record_versions')->restrictOnDelete();
            $table->string('amendment_type', 20);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->string('status', 20)->default('requested');
            $table->string('pending_key', 40)->nullable()->unique();
            $table->json('reviewer_credential_snapshot')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('applied_version_id')->nullable()->constrained('medical_record_versions')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['medical_record_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_amendments');
        Schema::dropIfExists('medical_record_versions');
    }
};
