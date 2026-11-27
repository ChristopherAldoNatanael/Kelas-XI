<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifiable_disease_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('encounter_id')->constrained('clinical_encounters')->cascadeOnDelete();
            $table->string('disease_code', 60);
            $table->string('disease_name', 150);
            $table->string('classification', 20);
            $table->timestamp('indicated_at');
            $table->timestamp('report_deadline_at');
            $table->string('status', 20)->default('draft');
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('isikhnas_reference', 120)->nullable();
            $table->string('evidence_path', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status'], 'ndr_clinic_status_index');
            $table->index(['status', 'report_deadline_at'], 'ndr_status_deadline_index');
            $table->index(['medical_record_id', 'disease_code'], 'ndr_record_disease_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifiable_disease_reports');
    }
};
