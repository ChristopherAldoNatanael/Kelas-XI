<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesanan Pet Shop + jejak stok.
 *
 * Reservasi stok disimpan sebagai agregat di `product_variants.stock_reserved`
 * (bukan tabel reservasi terpisah) supaya tidak ada dua sumber kebenaran yang
 * bisa berbeda. Pelepasan/komitmen reservasi dijaga idempoten lewat kolom
 * `stock_released_at` / `stock_committed_at` pada order di dalam row lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();

            $table->string('status', 32)->default('pending_payment');
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('payment_method', 20)->nullable();

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3)->default('IDR');

            // Snapshot lokasi pengambilan: klinik boleh berganti alamat/nama,
            // tetapi riwayat pesanan harus tetap menampilkan data saat transaksi.
            $table->string('pickup_clinic_name', 160);
            $table->text('pickup_clinic_address');
            $table->string('pickup_clinic_phone', 32)->nullable();

            $table->string('customer_name', 120);
            $table->string('customer_phone', 32)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('stock_reserved_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->timestamp('stock_committed_at')->nullable();
            $table->timestamp('stock_released_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'payment_status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['clinic_id', 'created_at']);
        });

        Schema::create('product_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->foreignId('clinic_product_id')->nullable()->constrained('clinic_products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshot: detail pesanan historis tidak boleh berubah saat produk
            // diedit, diganti harga, atau diarsipkan.
            $table->string('product_name', 160);
            $table->string('variant_name', 120);
            $table->string('sku', 64);
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();

            $table->index('product_order_id');
        });

        Schema::create('product_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_order_id')->constrained('product_orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('source', 24)->default('system'); // system|user|clinic_admin|webhook
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['product_order_id', 'created_at']);
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('clinic_product_id')->constrained('clinic_products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('type', 24); // purchase|adjustment|reservation|release|sale|cancellation|return
            // `quantity` bertanda sesuai jenis movement:
            //  - purchase/adjustment/return/sale: delta stok FISIK (on_hand)
            //  - reservation/release/cancellation: delta stok TERESERVASI
            // `stock_after` = on_hand setelah aksi, `reserved_after` = reserved
            // setelah aksi. Keduanya disimpan agar audit tidak perlu menebak.
            $table->integer('quantity');
            $table->unsignedInteger('stock_after');
            $table->unsignedInteger('reserved_after')->default(0);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'type']);
            $table->index(['clinic_product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_order_status_histories');
        Schema::dropIfExists('product_order_items');
        Schema::dropIfExists('product_orders');
    }
};
