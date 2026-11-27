<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 80);
            $table->string('name', 150);
            $table->string('species', 40)->nullable();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->boolean('is_current')->default(true);
            $table->string('active_key', 160)->nullable()->unique('clinical_templates_active_key_unique');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'template_key', 'version'], 'clinical_templates_clinic_key_version_unique');
            $table->index(['clinic_id', 'is_current'], 'clinical_templates_clinic_current_index');
        });

        Schema::create('medical_record_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('medical_record_versions')->nullOnDelete();
            $table->string('format', 10);
            $table->string('purpose', 40);
            $table->string('recipient_type', 40)->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->char('token_hash', 64)->nullable()->unique('medical_record_exports_token_hash_unique');
            $table->timestamp('token_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('last_accessed_ip', 45)->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'medical_record_id'], 'mre_clinic_record_index');
            $table->index(['expires_at', 'token_used_at'], 'mre_expiry_usage_index');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->string('direction', 20)->default('outgoing')->after('clinic_id');
            $table->string('referral_number', 40)->nullable()->after('direction');
            $table->string('external_facility', 150)->nullable()->after('referred_to');
            $table->text('summary')->nullable()->after('notes');
            $table->char('document_hash', 64)->nullable()->after('summary');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->unique(['clinic_id', 'referral_number'], 'referrals_clinic_number_unique');
            $table->index(['clinic_id', 'direction'], 'referrals_clinic_direction_index');
        });

        Schema::create('inpatient_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encounter_id')->unique()->constrained('clinical_encounters')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();
            $table->string('kennel_code', 40)->nullable();
            $table->string('ward', 60)->nullable();
            $table->string('status', 20)->default('admitted');
            $table->foreignId('admitting_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->text('discharge_summary')->nullable();
            $table->text('discharge_instructions')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status'], 'inpatient_stays_clinic_status_index');
            $table->index(['medical_record_id', 'admitted_at'], 'inpatient_stays_record_admitted_index');
        });

        Schema::create('inpatient_location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inpatient_stay_id')->constrained('inpatient_stays')->cascadeOnDelete();
            $table->string('from_ward', 60)->nullable();
            $table->string('from_kennel_code', 40)->nullable();
            $table->string('to_ward', 60)->nullable();
            $table->string('to_kennel_code', 40)->nullable();
            $table->timestamp('moved_at');
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['inpatient_stay_id', 'moved_at'], 'inpatient_logs_stay_moved_index');
        });

        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inpatient_stay_id')->constrained('inpatient_stays')->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->string('order_source', 20);
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete();
            $table->decimal('dose_value', 10, 3)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->string('route', 40)->nullable();
            $table->timestamp('administered_at');
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('given');
            $table->string('idempotency_key', 80)->unique('medication_admin_idempotency_unique');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inpatient_stay_id', 'administered_at'], 'med_admin_stay_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
        Schema::dropIfExists('inpatient_location_logs');
        Schema::dropIfExists('inpatient_stays');

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropUnique('referrals_clinic_number_unique');
            $table->dropIndex('referrals_clinic_direction_index');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn([
                'direction', 'referral_number', 'external_facility', 'summary',
                'document_hash', 'verified_at', 'voided_at', 'void_reason',
            ]);
        });

        Schema::dropIfExists('medical_record_exports');
        
        Schema::dropIfExists('clinical_templates');
    }
};
