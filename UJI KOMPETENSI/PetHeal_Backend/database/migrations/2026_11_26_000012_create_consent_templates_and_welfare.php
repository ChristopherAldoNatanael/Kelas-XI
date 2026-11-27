<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2.2 — template consent berversi, bukti persetujuan, penilaian kesejahteraan,
 * dan pencatatan dasar tindakan darurat.
 *
 * Tidak ada kolom yang mengubah status kredensial tenaga. Tindakan darurat
 * dicatat sebagai fakta klinis, bukan sebagai pemberian kewenangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('template_key', 80);
            $table->string('name', 150);
            $table->string('consent_type', 40);
            $table->unsignedInteger('version')->default(1);
            $table->text('body');
            $table->char('body_hash', 64);
            $table->boolean('is_current')->default(true);
            $table->string('active_key', 160)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['clinic_id', 'template_key', 'version'],
                'consent_templates_clinic_key_version_unique'
            );
            $table->index(['clinic_id', 'consent_type', 'is_current'], 'consent_templates_lookup_idx');
        });

        Schema::create('animal_welfare_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('clinical_encounters')->nullOnDelete();
            $table->unsignedTinyInteger('freedom_hunger_thirst');
            $table->unsignedTinyInteger('freedom_discomfort');
            $table->unsignedTinyInteger('freedom_pain_injury_disease');
            $table->unsignedTinyInteger('freedom_fear_distress');
            $table->unsignedTinyInteger('freedom_natural_behavior');
            $table->unsignedTinyInteger('overall_score');
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'assessed_at'], 'animal_welfare_clinic_time_idx');
            $table->index(['medical_record_id', 'assessed_at'], 'animal_welfare_record_time_idx');
            $table->index(['encounter_id', 'assessed_at'], 'animal_welfare_encounter_time_idx');
        });

        Schema::table('treatment_consents', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('id')
                ->constrained('clinics')->nullOnDelete();
            $table->string('template_key', 80)->nullable()->after('consent_type');
            $table->unsignedInteger('template_version')->nullable()->after('template_key');
            $table->char('consent_text_hash', 64)->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->char('evidence_hash', 64)->nullable();
            $table->string('guardian_name', 120)->nullable();
            $table->string('guardian_relation', 40)->nullable();
            $table->string('emergency_basis', 50)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['clinic_id', 'consent_type'], 'treatment_consents_clinic_type_idx');
            $table->index(['clinic_id', 'revoked_at'], 'treatment_consents_clinic_revoked_idx');
        });

        Schema::table('consents', function (Blueprint $table) {
            $table->char('policy_hash', 64)->nullable()->after('policy_version');
            $table->timestamp('withdrawn_at')->nullable()->after('revoked_at');
            $table->string('withdrawal_reason', 255)->nullable();
            $table->string('evidence_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn(['policy_hash', 'withdrawn_at', 'withdrawal_reason', 'evidence_path']);
        });

        Schema::table('treatment_consents', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropForeign(['revoked_by']);
            $table->dropIndex('treatment_consents_clinic_type_idx');
            $table->dropIndex('treatment_consents_clinic_revoked_idx');
            $table->dropColumn([
                'clinic_id', 'template_key', 'template_version', 'consent_text_hash',
                'evidence_path', 'evidence_hash', 'guardian_name', 'guardian_relation',
                'emergency_basis', 'revoked_at', 'revoked_reason', 'revoked_by',
            ]);
        });

        Schema::dropIfExists('animal_welfare_assessments');
        Schema::dropIfExists('consent_templates');
    }
};
