<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1/M3 — kolom lifecycle, versi aktif, retensi, dan audit request.
 *
 * Semua kolom nullable/default aman. Nilai status data lama tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('content_hash')
                ->unique()->constrained('medical_record_versions')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('current_version_id');
            $table->timestamp('in_review_at')->nullable()->after('finalized_by');
            $table->foreignId('in_review_by')->nullable()->after('in_review_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('last_amended_at')->nullable()->after('in_review_by');
            $table->date('retention_until')->nullable()->after('archived_at');
            $table->boolean('legal_hold')->default(false)->after('retention_until');
            $table->text('legal_hold_reason')->nullable()->after('legal_hold');
            $table->timestamp('legal_hold_set_at')->nullable()->after('legal_hold_reason');
            $table->foreignId('legal_hold_set_by')->nullable()->after('legal_hold_set_at')
                ->constrained('users')->nullOnDelete();
            $table->string('template_key', 80)->nullable()->after('legal_hold_set_by');
            $table->unsignedInteger('template_version')->nullable()->after('template_key');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->after('clinic_id');
            $table->string('result', 20)->nullable()->after('request_id');
            $table->string('reason', 120)->nullable()->after('result');
            $table->json('metadata')->nullable()->after('reason');
            $table->index(['request_id']);
            $table->index(['result', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropIndex(['result', 'created_at']);
            $table->dropColumn(['request_id', 'result', 'reason', 'metadata']);
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->string('status', 16)->default('final')->change();
            $table->dropUnique(['current_version_id']);
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn([
                'version', 'in_review_at', 'in_review_by', 'last_amended_at',
                'retention_until', 'legal_hold', 'legal_hold_reason',
                'legal_hold_set_at', 'legal_hold_set_by', 'template_key',
                'template_version',
            ]);
        });
    }
};
