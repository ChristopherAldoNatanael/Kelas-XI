<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_record_exports', function (Blueprint $table) {
            $table->string('rendered_path', 255)->nullable()->after('file_hash');
            $table->char('rendered_hash', 64)->nullable()->after('rendered_path');
            $table->char('content_hash', 64)->nullable()->after('rendered_hash');
            $table->string('signature_path', 255)->nullable()->after('content_hash');
            $table->char('signature_hash', 64)->nullable()->after('signature_path');
            $table->timestamp('signed_at')->nullable()->after('signature_hash');
            $table->foreignId('signed_by')->nullable()->after('signed_at')
                ->constrained('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');

            DB::unprepared("
                CREATE TRIGGER audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'audit_logs bersifat append-only'
            ");

            DB::unprepared("
                CREATE TRIGGER audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'audit_logs tidak boleh dihapus'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        }

        Schema::table('medical_record_exports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_by');
            $table->dropColumn([
                'rendered_path', 'rendered_hash', 'content_hash',
                'signature_path', 'signature_hash', 'signed_at',
            ]);
        });
    }
};
