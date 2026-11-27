<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisi bersifat append-only.
 *
 * Baris tanpa entry_hash masih boleh diisi sekali oleh
 * `audit:backfill-chain`; setelah itu UPDATE ditolak. DELETE selalu ditolak.
 * Aplikasi tidak boleh bergantung pada UI saja untuk jaminan audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS medical_record_revisions_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS medical_record_revisions_no_delete');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER medical_record_revisions_no_update
BEFORE UPDATE ON medical_record_revisions
FOR EACH ROW
BEGIN
    IF OLD.entry_hash IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'medical_record_revisions bersifat append-only';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER medical_record_revisions_no_delete
BEFORE DELETE ON medical_record_revisions
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'medical_record_revisions tidak boleh dihapus';
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS medical_record_revisions_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS medical_record_revisions_no_delete');
    }
};
