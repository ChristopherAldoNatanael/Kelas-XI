<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4.6 — berkas jawaban PDP yang diunggah/dibuat dari UI admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table) {
            $table->string('response_file_path')->nullable()->after('response_path');
            $table->string('response_file_name')->nullable()->after('response_file_path');
            $table->string('response_file_mime', 100)->nullable()->after('response_file_name');
            $table->unsignedBigInteger('response_file_size')->nullable()->after('response_file_mime');
            $table->string('response_file_sha256', 64)->nullable()->after('response_file_size');
            $table->boolean('response_file_encrypted')->default(false)->after('response_file_sha256');
            $table->unsignedInteger('response_download_count')->default(0)->after('response_file_encrypted');
            $table->timestamp('response_last_downloaded_at')->nullable()->after('response_download_count');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table) {
            $table->dropColumn([
                'response_file_path',
                'response_file_name',
                'response_file_mime',
                'response_file_size',
                'response_file_sha256',
                'response_file_encrypted',
                'response_download_count',
                'response_last_downloaded_at',
            ]);
        });
    }
};
