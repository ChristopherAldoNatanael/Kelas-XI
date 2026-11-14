<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment domain terpusat.
 *
 * Satu `payments` melayani consultation dan pet_shop lewat referensi
 * (`payable_type` + `payable_id`), tetapi order/stock tetap di domain masing-
 * masing. Merchant ID & split disimpan sebagai snapshot agar perubahan
 * konfigurasi klinik tidak mengubah histori transaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_merchant_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained('clinics')->cascadeOnDelete();
            $table->string('provider', 32)->default('midtrans');
            $table->string('environment', 16)->default('sandbox');
            $table->string('merchant_id', 64)->nullable();
            $table->string('partner_id', 64)->nullable();
            $table->string('onboarding_status', 32)->default('not_started');
            $table->boolean('is_online_payment_enabled')->default(false);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['environment', 'onboarding_status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_code', 48)->unique();
            $table->string('provider', 32)->default('midtrans');
            $table->string('payment_type', 24); // consultation|pet_shop
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('payable_type', 80);
            $table->unsignedBigInteger('payable_id');

            $table->string('provider_order_id', 64)->unique();
            $table->string('provider_transaction_id', 96)->nullable();
            $table->string('partner_id', 64)->nullable();
            $table->string('merchant_id', 64)->nullable();
            $table->string('environment', 16)->default('sandbox');

            $table->unsignedBigInteger('gross_amount');
            $table->char('currency', 3)->default('IDR');
            $table->string('payment_method', 40)->nullable();

            $table->string('status', 32)->default('unpaid');
            $table->string('provider_status', 40)->nullable();
            $table->text('snap_token')->nullable();
            $table->text('redirect_url')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('refunded_amount')->default(0);

            $table->json('split_settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->string('reconcile_note', 255)->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['payment_type', 'status']);
            $table->index('payable_type', 'payments_payable_type_index');
            $table->index(['payable_type', 'payable_id'], 'payments_payable_index');
            $table->index('created_at');
        });

        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 32)->default('midtrans');
            $table->string('provider_order_id', 64)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('transaction_status', 40)->nullable();
            $table->string('status_code', 10)->nullable();
            $table->string('gross_amount', 32)->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->string('processing_note', 255)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();

            $table->index('provider_order_id');
            $table->index(['processed', 'created_at']);
        });

        Schema::create('payment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('provider_status', 40)->nullable();
            $table->string('source', 24)->default('webhook'); // webhook|reconciliation|system|clinic_admin|user
            $table->string('note', 255)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('refund_code', 48)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('reason', 255)->nullable();
            $table->string('status', 32)->default('requested');
            $table->string('provider_refund_id', 96)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_status_histories');
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('clinic_merchant_configs');
    }
};
