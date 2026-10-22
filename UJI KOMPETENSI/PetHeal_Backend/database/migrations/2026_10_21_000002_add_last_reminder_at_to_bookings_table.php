<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 (Operational Trust & Proof): status reminder.
 *
 * Diisi HANYA ketika pengiriman reminder berhasil (manual maupun otomatis).
 * Dipakai untuk menampilkan "Terakhir diingatkan ..." di detail booking dan
 * mencegah reminder otomatis terkirim dua kali pada hari yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'last_reminder_at')) {
                $table->timestamp('last_reminder_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'last_reminder_at')) {
                $table->dropColumn('last_reminder_at');
            }
        });
    }
};
