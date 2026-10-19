<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            // 'register' = verifikasi pendaftaran, 'reset' = reset kata sandi.
            $table->string('purpose', 20);
            $table->string('code_hash');
            // Data pendaftaran sementara (nama/telepon/slug/hash password)
            // supaya kirim ulang kode tidak perlu user mengisi form lagi.
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // Satu kode aktif per email+tujuan; kode baru menimpa yang lama.
            $table->unique(['email', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
