<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan akun login portal dokter.
 *
 * Baris `doctors` tetap menjadi sumber data praktik (spesialisasi, jadwal,
 * booking), sedangkan `users` menyimpan kredensial. Pemisahan ini menjaga data
 * dokter lama yang belum punya akun tetap valid — `user_id` nullable, dan unik
 * supaya satu akun tidak bisa dipakai dua profil dokter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
