<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2.1 — order, item, spesimen, rentang referensi, dan verifikasi hasil lab.
 *
 * `lab_results` lama tidak dihapus. Kolom baru nullable agar data legacy tetap
 * terbaca, lalu diisi oleh command backfill. Hasil legacy sengaja tidak
 * dianggap terverifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('medical_record_id')->constrained('medical_records')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('clinical_encounters')->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->string('priority', 20)->default('routine');
            $table->string('status', 20)->default('draft');
            $table->text('clinical_notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'ordered_at'], 'lab_orders_clinic_status_idx');
            $table->index(['medical_record_id', 'ordered_at'], 'lab_orders_record_idx');
            $table->index(['encounter_id', 'ordered_at'], 'lab_orders_encounter_idx');
        });

        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('test_code', 40)->nullable();
            $table->string('test_name', 120);
            $table->string('specimen_type', 60)->nullable();
            $table->string('status', 20)->default('ordered');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status'], 'lab_order_items_clinic_status_idx');
            $table->index(['lab_order_id', 'status'], 'lab_order_items_order_status_idx');
            $table->index(['test_code'], 'lab_order_items_test_code_idx');
        });

        Schema::create('lab_specimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('lab_order_id')->constrained('lab_orders')->cascadeOnDelete();
            $table->string('accession_number', 60);
            $table->string('specimen_type', 60);
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending_collection');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'accession_number'], 'lab_specimens_clinic_accession_unique');
            $table->index(['lab_order_id', 'status'], 'lab_specimens_order_status_idx');
            $table->index(['clinic_id', 'status', 'collected_at'], 'lab_specimens_clinic_status_idx');
        });

        Schema::create('lab_reference_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('species', 40);
            $table->string('breed', 80)->nullable();
            $table->string('sex', 20)->nullable();
            $table->unsignedInteger('age_min_days')->nullable();
            $table->unsignedInteger('age_max_days')->nullable();
            $table->string('test_code', 40);
            $table->string('parameter', 120);
            $table->string('unit', 30)->nullable();
            $table->decimal('reference_low', 12, 4)->nullable();
            $table->decimal('reference_high', 12, 4)->nullable();
            $table->string('reference_text', 120)->nullable();
            $table->string('method', 80)->nullable();
            $table->string('analyzer', 80)->nullable();
            $table->string('specimen_type', 60)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'species', 'test_code', 'parameter'], 'lab_ref_lookup_idx');
            $table->index(['clinic_id', 'is_active'], 'lab_ref_clinic_active_idx');
        });

        Schema::table('lab_results', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('id')
                ->constrained('clinics')->nullOnDelete();
            $table->foreignId('lab_order_id')->nullable()->after('medical_record_id')
                ->constrained('lab_orders')->nullOnDelete();
            $table->foreignId('lab_order_item_id')->nullable()->after('lab_order_id')
                ->constrained('lab_order_items')->nullOnDelete();
            $table->foreignId('lab_specimen_id')->nullable()->after('lab_order_item_id')
                ->constrained('lab_specimens')->nullOnDelete();
            $table->string('species', 40)->nullable()->after('parameter');
            $table->decimal('reference_low', 12, 4)->nullable();
            $table->decimal('reference_high', 12, 4)->nullable();
            $table->string('reference_text', 120)->nullable();
            $table->string('reference_status', 30)->nullable();
            $table->string('status', 20)->default('pending')->after('flag');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('resulted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 80)->nullable();
            $table->foreignId('reference_range_id')->nullable()
                ->constrained('lab_reference_ranges')->nullOnDelete();
            $table->boolean('is_additional')->default(false);
            $table->foreignId('additional_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('correction_of_id')->nullable()->constrained('lab_results')->nullOnDelete();
            $table->string('correction_reason', 255)->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['clinic_id', 'status'], 'lab_results_clinic_status_idx');
            $table->index(['lab_order_id', 'status'], 'lab_results_order_status_idx');
            $table->index(['lab_order_item_id'], 'lab_results_order_item_idx');
            $table->index(['correction_of_id'], 'lab_results_correction_idx');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS lab_results_verified_immutable');
        DB::unprepared('DROP TRIGGER IF EXISTS lab_results_verified_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS lab_results_legacy_no_verify');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER lab_results_verified_immutable
BEFORE UPDATE ON lab_results
FOR EACH ROW
BEGIN
    IF OLD.status IN ('verified', 'corrected') THEN
        IF NEW.status NOT IN ('verified', 'corrected', 'void') THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'lab_results verified hanya boleh tetap atau void';
        END IF;

        IF NEW.status <> 'void' AND (
            NOT (NEW.test_name <=> OLD.test_name)
            OR NOT (NEW.parameter <=> OLD.parameter)
            OR NOT (NEW.value <=> OLD.value)
            OR NOT (NEW.unit <=> OLD.unit)
            OR NOT (NEW.reference_low <=> OLD.reference_low)
            OR NOT (NEW.reference_high <=> OLD.reference_high)
            OR NOT (NEW.reference_text <=> OLD.reference_text)
            OR NOT (NEW.flag <=> OLD.flag)
            OR NOT (NEW.reference_status <=> OLD.reference_status)
            OR NOT (NEW.verified_by <=> OLD.verified_by)
            OR NOT (NEW.verified_at <=> OLD.verified_at)
            OR NOT (NEW.correction_of_id <=> OLD.correction_of_id)
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'lab_results verified tidak boleh diubah; buat koreksi baru';
        END IF;

        IF NEW.status = 'void' AND (
            NEW.void_reason IS NULL
            OR NEW.voided_at IS NULL
            OR NEW.voided_by IS NULL
            OR NOT (NEW.value <=> OLD.value)
            OR NOT (NEW.test_name <=> OLD.test_name)
            OR NOT (NEW.parameter <=> OLD.parameter)
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'void lab_results wajib beralasan dan tidak boleh mengubah isi';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER lab_results_legacy_no_verify
BEFORE UPDATE ON lab_results
FOR EACH ROW
BEGIN
    IF OLD.status = 'legacy' AND NEW.status = 'verified' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'hasil legacy tidak boleh langsung menjadi verified; gunakan koreksi';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER lab_results_verified_no_delete
BEFORE DELETE ON lab_results
FOR EACH ROW
BEGIN
    IF OLD.status IN ('verified', 'corrected', 'legacy') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'lab_results verified/corrected/legacy tidak boleh dihapus';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS lab_results_verified_immutable');
            DB::unprepared('DROP TRIGGER IF EXISTS lab_results_verified_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS lab_results_legacy_no_verify');
        }

        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropForeign(['lab_order_id']);
            $table->dropForeign(['lab_order_item_id']);
            $table->dropForeign(['lab_specimen_id']);
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['reference_range_id']);
            $table->dropForeign(['additional_approved_by']);
            $table->dropForeign(['correction_of_id']);
            $table->dropForeign(['voided_by']);

            $table->dropIndex('lab_results_clinic_status_idx');
            $table->dropIndex('lab_results_order_status_idx');
            $table->dropIndex('lab_results_order_item_idx');
            $table->dropIndex('lab_results_correction_idx');

            $table->dropColumn([
                'clinic_id', 'lab_order_id', 'lab_order_item_id', 'lab_specimen_id',
                'species', 'reference_low', 'reference_high', 'reference_text',
                'reference_status', 'status', 'collected_at', 'resulted_at',
                'verified_at', 'verified_by', 'method', 'reference_range_id',
                'is_additional', 'additional_approved_by', 'correction_of_id',
                'correction_reason', 'void_reason', 'voided_at', 'voided_by',
            ]);
        });

        Schema::dropIfExists('lab_reference_ranges');
        Schema::dropIfExists('lab_specimens');
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
    }
};
