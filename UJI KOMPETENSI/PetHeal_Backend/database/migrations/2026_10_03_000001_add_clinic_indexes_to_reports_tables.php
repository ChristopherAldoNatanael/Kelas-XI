<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 3 (D-03): medical_records/payment_events carry clinic_id with a
     * foreign key but no index. Tenant-filtered reads on growing tables need
     * one. Index-only migration — no column or data change.
     */
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->index('clinic_id', 'idx_medical_records_clinic_id');
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->index('clinic_id', 'idx_payment_events_clinic_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropIndex('idx_payment_events_clinic_id');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_medical_records_clinic_id');
        });
    }
};
