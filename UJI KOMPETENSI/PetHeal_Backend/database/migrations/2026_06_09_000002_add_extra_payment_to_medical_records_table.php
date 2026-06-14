<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->decimal('total_medical_cost', 12, 2)->default(0)->after('medicine_cost');
            $table->decimal('extra_payment_amount', 12, 2)->default(0)->after('total_medical_cost');
            $table->decimal('extra_payment_paid_amount', 12, 2)->default(0)->after('extra_payment_amount');
            $table->string('extra_payment_status')->default('not_required')->after('extra_payment_paid_amount');
            $table->string('extra_payment_order_id')->nullable()->unique()->after('extra_payment_status');
            $table->timestamp('extra_payment_date')->nullable()->after('extra_payment_order_id');

            $table->index(['extra_payment_status', 'created_at'], 'idx_medical_records_extra_payment_status');
        });

        Schema::table('payment_events', function (Blueprint $table) {
            $table->foreignId('medical_record_id')->nullable()->after('booking_id')->constrained('medical_records')->cascadeOnDelete();
            $table->index(['medical_record_id', 'created_at'], 'idx_payment_events_medical_record_created');
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropIndex('idx_payment_events_medical_record_created');
            $table->dropConstrainedForeignId('medical_record_id');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('idx_medical_records_extra_payment_status');
            $table->dropUnique(['extra_payment_order_id']);
            $table->dropColumn([
                'total_medical_cost',
                'extra_payment_amount',
                'extra_payment_paid_amount',
                'extra_payment_status',
                'extra_payment_order_id',
                'extra_payment_date',
            ]);
        });
    }
};
