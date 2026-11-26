<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel klinis pendukung rekam medis.
 *
 * Dipisah dari `medical_records` supaya isi utama tetap ringkas dan setiap
 * komponen bisa tumbuh sendiri (vital per kunjungan, alergi per hewan,
 * persetujuan per tindakan, rujukan, dan hasil laboratorium terstruktur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->unique()
                ->constrained('medical_records')->cascadeOnDelete();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('crt_seconds', 3, 1)->nullable();
            $table->string('mucosa', 50)->nullable();
            $table->string('hydration', 30)->nullable();
            $table->unsignedTinyInteger('bcs')->nullable();          // 1-9
            $table->unsignedTinyInteger('pain_score')->nullable();   // 0-4
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pet_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('allergen', 150);
            $table->string('reaction', 255)->nullable();
            $table->string('severity', 20)->default('sedang');       // ringan|sedang|berat
            $table->boolean('is_active')->default(true);
            $table->foreignId('noted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('noted_at')->nullable();
            $table->timestamps();

            $table->index(['pet_id', 'is_active'], 'pet_allergies_pet_active_index');
        });

        Schema::create('treatment_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->nullable()
                ->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('consent_type', 40);                      // tindakan|anestesi|euthanasia|rawat_inap
            $table->text('risks_explained')->nullable();
            $table->string('signer_name', 120);
            $table->string('signer_relation', 40)->default('pemilik');
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('witness_name', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['medical_record_id', 'consent_type'], 'treatment_consents_record_type_index');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referred_to', 150);
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('sent');           // sent|accepted|returned|cancelled
            $table->timestamp('referred_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('result_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('test_name', 120);
            $table->string('parameter', 120);
            $table->string('value', 60);
            $table->string('unit', 30)->nullable();
            $table->string('reference_range', 60)->nullable();
            $table->string('flag', 10)->nullable();                  // normal|high|low
            $table->timestamp('performed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['medical_record_id', 'test_name'], 'lab_results_record_test_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('treatment_consents');
        Schema::dropIfExists('pet_allergies');
        Schema::dropIfExists('medical_record_vitals');
    }
};
