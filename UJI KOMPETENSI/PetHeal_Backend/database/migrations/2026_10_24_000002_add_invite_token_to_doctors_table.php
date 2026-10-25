<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan aktivasi akun dokter tanpa kode OTP.
 *
 * Yang disimpan hanya hash SHA-256 dari token; token asli hanya ada di email
 * dokter. Tautan kedaluwarsa 60 menit dan hangus setelah dipakai, sehingga
 * tidak ada kode tambahan yang perlu diketik ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('user_id');
            $table->timestamp('invite_token_expires_at')->nullable()->after('invite_token');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique(['invite_token']);
            $table->dropColumn(['invite_token', 'invite_token_expires_at']);
        });
    }
};
