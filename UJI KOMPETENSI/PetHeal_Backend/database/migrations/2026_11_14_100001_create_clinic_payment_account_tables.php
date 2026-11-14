<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routing pembayaran Midtrans per klinik.
 *
 * - `clinic_payment_accounts`  : satu akun pembayaran per klinik (status & environment aktif).
 * - `payment_credential_versions`: riwayat kredensial; Server Key terenkripsi oleh cast model.
 * - kolom baru di `payments`    : route yang dipakai transaksi (tidak pernah dihitung ulang).
 * - `payment_routing_logs`      : jejak percobaan routing yang ditolak (tanpa secret).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('midtrans');

            // not_configured | pending_verification | active | invalid | disabled | suspended
            $table->string('status', 32)->default('not_configured');
            $table->string('active_environment', 16)->nullable();
            $table->unsignedBigInteger('current_credential_version_id')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->string('last_validation_message', 255)->nullable();

            // Aktivasi production butuh persetujuan super admin.
            $table->timestamp('production_approved_at')->nullable();
            $table->foreignId('production_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });

        Schema::create('payment_credential_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_account_id')->constrained('clinic_payment_accounts')->cascadeOnDelete();
            $table->string('environment', 16);

            $table->string('merchant_id', 64)->nullable();
            $table->string('client_key', 190)->nullable();
            // Ciphertext (Laravel encrypted cast). Tidak pernah dikembalikan API/log/UI.
            $table->text('server_key')->nullable();

            $table->unsignedInteger('version')->default(1);

            // pending | valid | invalid | retired
            $table->string('status', 24)->default('pending');
            $table->boolean('is_active')->default(false);
            $table->string('validation_message', 255)->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payment_account_id', 'environment', 'version'], 'payment_credential_versions_unique');
            $table->index(['payment_account_id', 'environment', 'status'], 'payment_credential_versions_lookup');
        });

        Schema::table('clinic_payment_accounts', function (Blueprint $table) {
            $table->foreign('current_credential_version_id', 'clinic_payment_accounts_current_version_fk')
                ->references('id')
                ->on('payment_credential_versions')
                ->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('partner_id')
                ->constrained('clinic_payment_accounts')->nullOnDelete();
            $table->foreignId('payment_credential_version_id')->nullable()->after('payment_account_id')
                ->constrained('payment_credential_versions')->nullOnDelete();

            // clinic_account | central_fallback | legacy_central | partner_merchant
            $table->string('routing_source', 32)->nullable()->after('payment_credential_version_id');
            $table->index('routing_source');
        });

        Schema::create('payment_routing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('payable_type', 190)->nullable();
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->string('environment', 16);
            $table->string('routing_source', 32)->nullable();
            $table->foreignId('payment_account_id')->nullable()->constrained('clinic_payment_accounts')->nullOnDelete();
            $table->foreignId('credential_version_id')->nullable()->constrained('payment_credential_versions')->nullOnDelete();
            $table->string('merchant_id', 64)->nullable();
            $table->string('outcome', 16); // resolved | rejected
            $table->string('reason', 64)->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_routing_logs');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_account_id']);
            $table->dropForeign(['payment_credential_version_id']);
            $table->dropIndex(['routing_source']);
            $table->dropColumn(['payment_account_id', 'payment_credential_version_id', 'routing_source']);
        });

        Schema::table('clinic_payment_accounts', function (Blueprint $table) {
            $table->dropForeign('clinic_payment_accounts_current_version_fk');
        });

        Schema::dropIfExists('payment_credential_versions');
        Schema::dropIfExists('clinic_payment_accounts');
    }
};
