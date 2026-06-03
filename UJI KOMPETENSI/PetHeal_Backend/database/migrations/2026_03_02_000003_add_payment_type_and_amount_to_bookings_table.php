<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('payment_type', ['dp', 'full'])->nullable()->after('payment_method');
            $table->decimal('dp_amount', 10, 2)->nullable()->after('payment_type');
            $table->decimal('total_amount', 10, 2)->nullable()->after('dp_amount');
            $table->decimal('paid_amount', 10, 2)->default(0)->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'dp_amount', 'total_amount', 'paid_amount']);
        });
    }
};
