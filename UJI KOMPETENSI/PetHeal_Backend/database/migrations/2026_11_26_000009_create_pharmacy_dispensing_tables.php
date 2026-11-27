<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1.2 — resep sebagai dokumen, dispensing, reversal, registrasi obat,
 * referensi dosis, dan metadata movement.
 *
 * Semua perubahan additif. Alur lama (potong stok saat resep) tetap berjalan
 * sebagai mode kompatibilitas; alur baru memakai dispensation service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('registration_number', 80)->nullable()->after('manufacturer');
            $table->string('registration_status', 20)->default('unknown')->after('registration_number');
            $table->date('registration_expires_at')->nullable()->after('registration_status');
            $table->string('approved_species', 255)->nullable()->after('registration_expires_at');
            $table->string('withdrawal_period', 80)->nullable()->after('approved_species');
            $table->index(['clinic_id', 'registration_status'], 'medicines_clinic_reg_status_idx');
        });

        Schema::create('dose_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('species', 40);
            $table->string('medicine_name');
            $table->string('drug_class')->nullable();
            $table->string('indication', 150)->nullable();
            $table->string('route', 50)->nullable();
            $table->decimal('dose_min', 12, 4)->nullable();
            $table->decimal('dose_max', 12, 4)->nullable();
            $table->string('dose_unit', 20);
            $table->string('dose_basis', 30)->default('fixed');
            $table->decimal('max_dose', 12, 4)->nullable();
            $table->string('source', 200);
            $table->string('source_version', 40)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'species', 'medicine_name'], 'dose_refs_clinic_species_med_idx');
            $table->index(['clinic_id', 'is_active'], 'dose_refs_clinic_active_idx');
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->string('prescription_number', 40);
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prescribed_at')->nullable();
            $table->string('status', 20)->default('issued');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'prescription_number'], 'prescriptions_clinic_number_unique');
            $table->index(['medical_record_id', 'status'], 'prescriptions_record_status_idx');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('medical_record_id')
                ->constrained('prescriptions')->nullOnDelete();
            $table->decimal('dose_value', 12, 4)->nullable()->after('dose');
            $table->string('dose_unit', 20)->nullable()->after('dose_value');
            $table->string('dose_basis', 30)->nullable()->after('dose_unit');
            $table->decimal('weight_at_prescription', 6, 2)->nullable()->after('dose_basis');
            $table->string('concentration', 50)->nullable()->after('weight_at_prescription');
            $table->decimal('calculated_dose', 12, 4)->nullable()->after('concentration');
            $table->string('dose_check_status', 20)->default('not_checked')->after('calculated_dose');
            $table->text('dose_check_notes')->nullable()->after('dose_check_status');
            $table->string('dosage_form', 50)->nullable()->after('dose_check_notes');
            $table->string('strength', 50)->nullable()->after('dosage_form');
            $table->decimal('total_quantity', 12, 2)->nullable()->after('quantity');
            $table->string('withdrawal_period', 80)->nullable()->after('total_quantity');
            $table->foreignId('dose_reference_id')->nullable()->after('withdrawal_period')
                ->constrained('dose_references')->nullOnDelete();
            $table->string('dose_reference_source', 120)->nullable()->after('dose_reference_id');
        });

        Schema::create('dispensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->string('dispensation_number', 40);
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispensed_at');
            $table->string('kind', 20)->default('dispense');
            $table->string('status', 20)->default('completed');
            $table->string('idempotency_key', 80)->unique();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'dispensation_number'], 'dispensations_clinic_number_unique');
            $table->index(['prescription_id', 'kind', 'status'], 'dispensations_rx_kind_status_idx');
            $table->index(['reversal_of_id'], 'dispensations_reversal_idx');
        });

        Schema::table('dispensations', function (Blueprint $table) {
            $table->foreign('reversal_of_id', 'dispensations_reversal_fk')
                ->references('id')->on('dispensations')->nullOnDelete();
        });

        Schema::create('dispensation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('dispensation_id')->constrained('dispensations')->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->constrained('prescription_items')->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 30)->nullable();
            $table->string('medicine_name');
            $table->string('batch_number', 80)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->unsignedBigInteger('reversal_of_item_id')->nullable();
            $table->unsignedBigInteger('original_batch_id')->nullable();
            $table->decimal('reversed_quantity', 12, 2)->nullable();
            $table->string('registration_status_snapshot', 20)->nullable();
            $table->text('eligibility_override_reason')->nullable();
            $table->timestamps();

            $table->index(['dispensation_id'], 'dispensation_items_dispensation_idx');
            $table->index(['prescription_item_id'], 'dispensation_items_prescription_item_idx');
            $table->index(['stock_movement_id'], 'dispensation_items_movement_idx');
            $table->index(['reversal_of_item_id'], 'dispensation_items_reversal_idx');
        });

        Schema::table('medicine_stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('dispensation_item_id')->nullable()->after('transaction_id');
            $table->string('reference_type', 40)->nullable()->after('medical_record_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->unsignedBigInteger('reversal_of_movement_id')->nullable()->after('idempotency_key');
            $table->string('movement_kind', 20)->nullable()->after('reversal_of_movement_id');
            $table->text('notes')->nullable()->after('movement_kind');

            $table->index(['dispensation_item_id'], 'stock_movements_dispensation_item_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_movements_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::table('medicine_stock_movements', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex('stock_movements_dispensation_item_idx');
            $table->dropIndex('stock_movements_reference_idx');
            $table->dropColumn([
                'dispensation_item_id', 'reference_type', 'reference_id',
                'idempotency_key', 'reversal_of_movement_id', 'movement_kind', 'notes',
            ]);
        });

        Schema::dropIfExists('dispensation_items');

        Schema::table('dispensations', function (Blueprint $table) {
            $table->dropForeign('dispensations_reversal_fk');
            $table->dropIndex('dispensations_clinic_number_unique');
            $table->dropIndex('dispensations_rx_kind_status_idx');
            $table->dropIndex('dispensations_reversal_idx');
        });

        Schema::dropIfExists('dispensations');

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_id');
            $table->dropConstrainedForeignId('dose_reference_id');
            $table->dropColumn([
                'dose_value', 'dose_unit', 'dose_basis', 'weight_at_prescription',
                'concentration', 'calculated_dose', 'dose_check_status',
                'dose_check_notes', 'dosage_form', 'strength', 'total_quantity',
                'withdrawal_period', 'dose_reference_source',
            ]);
        });

        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('dose_references');

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropIndex('medicines_clinic_reg_status_idx');
            $table->dropColumn([
                'registration_number', 'registration_status', 'registration_expires_at',
                'approved_species', 'withdrawal_period',
            ]);
        });
    }
};
