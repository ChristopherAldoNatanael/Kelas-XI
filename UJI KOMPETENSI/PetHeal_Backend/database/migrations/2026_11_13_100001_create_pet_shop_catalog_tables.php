<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog Pet Shop.
 *
 * Domain ini SENGAJA terpisah dari `medicines` / stok obat. Tidak ada foreign
 * key, trigger, atau shared table dengan modul medis, sehingga perubahan stok
 * produk tidak pernah menyentuh stok obat.
 *
 * Uang disimpan sebagai integer rupiah (tanpa desimal) untuk menghindari
 * floating point. Harga & stok selalu per varian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('icon', 80)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('clinic_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['clinic_id', 'slug']);
            $table->index(['clinic_id', 'is_active']);
            $table->index(['product_category_id', 'is_active']);
            $table->index('name');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_product_id')->constrained('clinic_products')->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name', 120);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('stock_reserved')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('clinic_products');
        Schema::dropIfExists('product_categories');
    }
};
