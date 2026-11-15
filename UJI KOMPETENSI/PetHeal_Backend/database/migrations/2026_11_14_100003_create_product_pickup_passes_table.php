<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pickup pass untuk Click & Collect.
 *
 * Token adalah nilai acak (bukan order id) yang hanya bisa dipakai sekali dan
 * dapat dicabut. Kode manual menjadi fallback saat kamera/QR tidak bisa dibaca.
 * Kolom `order_id` sengaja dipakai (bukan `product_order_id`) karena relasi
 * pass selalu satu arah ke pesanan; foreign key default Eloquent untuk
 * `ProductOrder::pickupPass()` adalah `product_order_id`, jadi model
 * mendeklarasikan nama kolomnya secara eksplisit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_pickup_passes')) {
            return;
        }

        Schema::create('product_pickup_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->string('manual_code', 12)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu pesanan hanya punya satu pass aktif; pass lama dicabut
            // (is_active = false) ketika pesanan dibatalkan / selesai.
            $table->index(['order_id', 'is_active']);
            $table->index(['clinic_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pickup_passes');
    }
};
