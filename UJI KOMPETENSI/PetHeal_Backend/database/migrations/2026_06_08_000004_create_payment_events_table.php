<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->string('transaction_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at'], 'idx_payment_events_booking_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
