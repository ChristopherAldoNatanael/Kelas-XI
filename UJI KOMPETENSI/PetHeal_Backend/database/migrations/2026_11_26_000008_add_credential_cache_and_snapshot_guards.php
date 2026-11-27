<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cache status kredensial di dokter (bukan sumber kebenaran) dan jaminan
 * snapshot kredensial tidak dapat diubah/dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('credential_status', 20)->nullable()->after('sip_expiry');
            $table->timestamp('credential_verified_at')->nullable()->after('credential_status');
        });

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS credential_snapshots_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS credential_snapshots_no_delete');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER credential_snapshots_no_update
BEFORE UPDATE ON medical_record_credential_snapshots
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'medical_record_credential_snapshots bersifat append-only';
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER credential_snapshots_no_delete
BEFORE DELETE ON medical_record_credential_snapshots
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'medical_record_credential_snapshots tidak boleh dihapus';
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS credential_snapshots_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS credential_snapshots_no_delete');
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn(['credential_status', 'credential_verified_at']);
        });
    }
};
