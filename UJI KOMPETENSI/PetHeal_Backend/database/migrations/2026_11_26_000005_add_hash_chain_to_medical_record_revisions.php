<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 — hash berantai pada revisi rekam medis.
 *
 * `entry_hash` mengikat isi baris dan `prev_hash` (hash baris sebelumnya untuk
 * rekam medis yang sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_record_revisions', function (Blueprint $table) {
            $table->char('prev_hash', 64)->nullable()->after('user_agent');
            $table->char('entry_hash', 64)->nullable()->after('prev_hash');
            $table->index(['medical_record_id', 'entry_hash'], 'medical_record_revisions_chain_index');
        });
    }

    public function down(): void
    {
        Schema::table('medical_record_revisions', function (Blueprint $table) {
            $table->dropIndex('medical_record_revisions_chain_index');
            $table->dropColumn(['prev_hash', 'entry_hash']);
        });
    }
};
