<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan pemrosesan data pribadi (UU 27/2022).
 *
 * Satu baris = satu persetujuan untuk satu tujuan pada satu versi kebijakan.
 * Pencabutan tidak menghapus baris (riwayat persetujuan harus bisa dibuktikan),
 * cukup mengisi `revoked_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 60);                    // layanan|pemasaran|pengingat|analitik
            $table->string('policy_version', 20);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'revoked_at'], 'consents_user_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
