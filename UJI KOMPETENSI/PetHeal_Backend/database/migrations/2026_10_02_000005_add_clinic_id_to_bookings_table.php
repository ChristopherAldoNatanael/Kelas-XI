<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('service_type')
                ->constrained('clinics')
                ->cascadeOnDelete();

            $table->index(['clinic_id', 'status', 'booking_date']);
            $table->index(['clinic_id', 'doctor_id', 'booking_date', 'booking_time']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropIndex(['clinic_id', 'status', 'booking_date']);
            $table->dropIndex(['clinic_id', 'doctor_id', 'booking_date', 'booking_time']);
            $table->dropColumn('clinic_id');
        });
    }
};