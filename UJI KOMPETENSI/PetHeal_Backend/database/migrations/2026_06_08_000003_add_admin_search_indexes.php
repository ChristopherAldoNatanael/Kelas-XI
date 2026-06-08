<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'idx_users_name');
            $table->index('phone', 'idx_users_phone');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->index('name', 'idx_pets_name');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['payment_status', 'updated_at'], 'idx_bookings_payment_status_updated');
            $table->index(['payment_type', 'updated_at'], 'idx_bookings_payment_type_updated');
            $table->index(['payment_method', 'updated_at'], 'idx_bookings_payment_method_updated');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_payment_status_updated');
            $table->dropIndex('idx_bookings_payment_type_updated');
            $table->dropIndex('idx_bookings_payment_method_updated');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropIndex('idx_pets_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_name');
            $table->dropIndex('idx_users_phone');
        });
    }
};
