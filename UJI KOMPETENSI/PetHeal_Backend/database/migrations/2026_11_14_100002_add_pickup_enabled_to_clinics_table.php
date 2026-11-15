<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Click & Collect: hanya klinik yang mengaktifkan pickup yang boleh
 * ditawarkan sebagai tempat pengambilan produk Pet Shop.
 *
 * Default true supaya klinik lama tidak tiba-tiba hilang dari katalog;
 * admin klinik bisa mematikannya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clinics', 'pickup_enabled')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->boolean('pickup_enabled')->default(true)->after('is_active');
            $table->index(['is_active', 'pickup_enabled']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('clinics', 'pickup_enabled')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'pickup_enabled']);
            $table->dropColumn('pickup_enabled');
        });
    }
};
