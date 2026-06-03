<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add indexes to improve query performance on frequently queried columns.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Index for slot availability checks
            $table->index(['doctor_id', 'booking_date', 'booking_time'], 'idx_doctor_date_time');
            
            // Index for user booking queries
            $table->index(['user_id', 'status'], 'idx_user_status');
            
            // Index for upcoming bookings query
            $table->index(['booking_date', 'status'], 'idx_date_status');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            // Index for next visit reminder queries
            $table->index('next_visit_date', 'idx_next_visit_date');
            
            // Index for pet medical history
            $table->index('pet_id', 'idx_pet_records');
        });

        Schema::table('pets', function (Blueprint $table) {
            // Index for user pet queries
            $table->index('user_id', 'idx_user_pets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_doctor_date_time');
            $table->dropIndex('idx_user_status');
            $table->dropIndex('idx_date_status');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_next_visit_date');
            $table->dropIndex('idx_pet_records');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropIndex('idx_user_pets');
        });
    }
};
