<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Triage untuk kunjungan darurat yang didaftarkan dari web admin.
 * Booking walk-in biasa membiarkan kolom ini NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('triage_level', 20)->nullable()->after('service_type');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('triage_level');
        });
    }
};
