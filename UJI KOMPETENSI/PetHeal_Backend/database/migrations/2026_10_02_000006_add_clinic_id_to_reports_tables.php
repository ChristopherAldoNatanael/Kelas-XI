<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('extra_payment_date')
                ->constrained('clinics')
                ->nullOnDelete();
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('medical_record_id')
                ->constrained('clinics')
                ->nullOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('user_agent')
                ->constrained('clinics')
                ->nullOnDelete();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('read_at')
                ->constrained('clinics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });
    }
};