<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak serah terima barang (Click & Collect).
 *
 * Disimpan terpisah dari status pesanan agar audit "siapa menyerahkan, kapan,
 * dari pass mana" tetap ada walaupun status pesanan berubah lagi. Hanya satu
 * baris per pesanan (unique) — proteksi idempotensi tambahan di level DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_orders', 'picked_up_at')) {
            return;
        }

        Schema::table('product_orders', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('completed_at');
            $table->foreignId('picked_up_by')->nullable()->after('picked_up_at')->constrained('users')->nullOnDelete();
        });

        if (!Schema::hasTable('product_pickup_handovers')) {
            Schema::create('product_pickup_handovers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_order_id')->unique()->constrained('product_orders')->cascadeOnDelete();
                $table->foreignId('product_pickup_pass_id')->nullable()->constrained('product_pickup_passes')->nullOnDelete();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('items_snapshot');
                $table->string('verification_method', 20)->default('manual');
                $table->timestamp('handed_over_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pickup_handovers');

        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('picked_up_by');
            $table->dropColumn('picked_up_at');
        });
    }
};
