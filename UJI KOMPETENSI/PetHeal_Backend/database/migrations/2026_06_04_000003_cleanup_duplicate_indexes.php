<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop duplicate indexes that overlap with 2026_04_06_000004.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_date_status');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropIndex('idx_user_pets');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_pet_records');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['booking_date', 'status'], 'idx_date_status');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->index('user_id', 'idx_user_pets');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->index('pet_id', 'idx_pet_records');
        });
    }
};
