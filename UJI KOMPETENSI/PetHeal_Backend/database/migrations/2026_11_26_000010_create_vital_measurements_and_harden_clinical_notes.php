<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1.3 — pengukuran vital berulang dan catatan klinis bertanda tangan.
 *
 * `medical_record_vitals` tetap dipertahankan sebagai snapshot terbaru agar
 * kontrak UI/API lama tidak berubah. Riwayat lengkap disimpan di
 * `vital_measurements`. Catatan klinis yang sudah `signed` tidak boleh diubah
 * isinya; koreksi harus membuat baris baru lewat `correction_of_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('encounter_id')->nullable()->constrained('clinical_encounters')->nullOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->foreignId('legacy_vital_id')->nullable()->unique()
                ->constrained('medical_record_vitals')->nullOnDelete();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('crt_seconds', 3, 1)->nullable();
            $table->string('mucosa', 50)->nullable();
            $table->string('hydration', 30)->nullable();
            $table->unsignedTinyInteger('bcs')->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('measured_at');
            $table->string('source', 20)->default('manual');
            $table->string('shift', 20)->nullable();
            $table->string('status', 20)->default('recorded');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'encounter_id', 'measured_at'], 'vital_measurements_encounter_idx');
            $table->index(['medical_record_id', 'measured_at'], 'vital_measurements_record_idx');
            $table->index(['pet_id', 'measured_at'], 'vital_measurements_pet_idx');
            $table->index(['clinic_id', 'status', 'measured_at'], 'vital_measurements_status_idx');
        });

        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->string('shift', 20)->nullable()->after('note_type');
            $table->index(['clinic_id', 'note_type', 'shift'], 'clinical_notes_type_shift_idx');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS clinical_notes_signed_immutable');
        DB::unprepared('DROP TRIGGER IF EXISTS clinical_notes_signed_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS vital_measurements_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS vital_measurements_no_update');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER clinical_notes_signed_immutable
BEFORE UPDATE ON clinical_notes
FOR EACH ROW
BEGIN
    IF OLD.status = 'signed' THEN
        IF NEW.status NOT IN ('signed', 'void') THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'clinical_notes signed hanya boleh tetap signed atau void';
        END IF;

        IF NEW.status = 'signed' AND (
            NOT (NEW.subjective <=> OLD.subjective)
            OR NOT (NEW.objective <=> OLD.objective)
            OR NOT (NEW.assessment <=> OLD.assessment)
            OR NOT (NEW.plan <=> OLD.plan)
            OR NOT (NEW.body <=> OLD.body)
            OR NOT (NEW.note_type <=> OLD.note_type)
            OR NOT (NEW.shift <=> OLD.shift)
            OR NOT (NEW.content_hash <=> OLD.content_hash)
            OR NOT (NEW.signed_by <=> OLD.signed_by)
            OR NOT (NEW.signed_at <=> OLD.signed_at)
            OR NOT (NEW.correction_of_id <=> OLD.correction_of_id)
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'clinical_notes signed tidak boleh diubah; gunakan koreksi';
        END IF;

        IF NEW.status = 'void' AND (
            NOT (NEW.subjective <=> OLD.subjective)
            OR NOT (NEW.objective <=> OLD.objective)
            OR NOT (NEW.assessment <=> OLD.assessment)
            OR NOT (NEW.plan <=> OLD.plan)
            OR NOT (NEW.body <=> OLD.body)
            OR NEW.void_reason IS NULL
            OR NEW.voided_at IS NULL
            OR NEW.voided_by IS NULL
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'void clinical_notes wajib beralasan dan tidak boleh mengubah isi';
        END IF;
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER clinical_notes_signed_no_delete
BEFORE DELETE ON clinical_notes
FOR EACH ROW
BEGIN
    IF OLD.status = 'signed' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'clinical_notes signed tidak boleh dihapus';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER vital_measurements_no_delete
BEFORE DELETE ON vital_measurements
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'vital_measurements tidak boleh dihapus';
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER vital_measurements_no_update
BEFORE UPDATE ON vital_measurements
FOR EACH ROW
BEGIN
    IF NOT (
        OLD.status = 'recorded'
        AND NEW.status = 'void'
        AND NEW.void_reason IS NOT NULL
        AND NEW.voided_at IS NOT NULL
        AND NEW.voided_by IS NOT NULL
        AND (NEW.temperature_c <=> OLD.temperature_c)
        AND (NEW.heart_rate <=> OLD.heart_rate)
        AND (NEW.respiratory_rate <=> OLD.respiratory_rate)
        AND (NEW.crt_seconds <=> OLD.crt_seconds)
        AND (NEW.mucosa <=> OLD.mucosa)
        AND (NEW.hydration <=> OLD.hydration)
        AND (NEW.bcs <=> OLD.bcs)
        AND (NEW.pain_score <=> OLD.pain_score)
        AND (NEW.weight_kg <=> OLD.weight_kg)
        AND (NEW.notes <=> OLD.notes)
        AND (NEW.measured_at <=> OLD.measured_at)
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'vital_measurements hanya boleh di-void, bukan diubah';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS clinical_notes_signed_immutable');
            DB::unprepared('DROP TRIGGER IF EXISTS clinical_notes_signed_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS vital_measurements_no_delete');
            DB::unprepared('DROP TRIGGER IF EXISTS vital_measurements_no_update');
        }

        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->dropIndex('clinical_notes_type_shift_idx');
            $table->dropColumn('shift');
        });

        Schema::dropIfExists('vital_measurements');
    }
};
