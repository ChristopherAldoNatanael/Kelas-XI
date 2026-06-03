<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes to frequently queried columns
     */
    public function up()
    {
        // Table: bookings
        Schema::table('bookings', function (Blueprint $table) {
            // Index for searching user bookings
            $table->index('user_id', 'idx_bookings_user_id');
            // Index for finding doctor's schedule
            $table->index('doctor_id', 'idx_bookings_doctor_id');
            // Index for calendar/schedule views
            $table->index('booking_date', 'idx_bookings_date');
            // Index for status filtering
            $table->index('status', 'idx_bookings_status');
            // Composite index for upcoming appointments (very common query)
            $table->index(['booking_date', 'status'], 'idx_bookings_date_status');
            // Index for payment filtering
            $table->index('payment_status', 'idx_bookings_payment_status');
        });

        // Table: pets
        Schema::table('pets', function (Blueprint $table) {
            $table->index('user_id', 'idx_pets_user_id');
        });

        // Table: medical_records
        Schema::table('medical_records', function (Blueprint $table) {
            $table->index('pet_id', 'idx_medical_records_pet_id');
            $table->index('booking_id', 'idx_medical_records_booking_id');
        });

        // Table: device_tokens
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->index('user_id', 'idx_device_tokens_user_id');
            $table->index('token', 'idx_device_tokens_token');
        });

        // Table: doctors
        Schema::table('doctors', function (Blueprint $table) {
            $table->index('is_active', 'idx_doctors_is_active');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_user_id');
            $table->dropIndex('idx_bookings_doctor_id');
            $table->dropIndex('idx_bookings_date');
            $table->dropIndex('idx_bookings_status');
            $table->dropIndex('idx_bookings_date_status');
            $table->dropIndex('idx_bookings_payment_status');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropIndex('idx_pets_user_id');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_medical_records_pet_id');
            $table->dropIndex('idx_medical_records_booking_id');
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_device_tokens_user_id');
            $table->dropIndex('idx_device_tokens_token');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex('idx_doctors_is_active');
        });
    }
};
