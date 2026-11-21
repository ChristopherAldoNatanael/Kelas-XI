<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus kolom `clinic_payment_accounts.server_key` yang tidak pernah dipakai.
 *
 * Server key Midtrans untuk setiap klinik disimpan TERENKRIPSI di
 * `payment_credential_versions.server_key` (cast `encrypted`), dan hanya
 * kolom itulah yang dibaca `PaymentAccountResolver`. Kolom lama di tabel akun
 * tetap `text` biasa — tidak pernah dibaca, tetapi berbahaya: siapa pun yang
 * kelak menulis ke sana akan menyimpan kunci pembayaran sebagai teks terbuka
 * yang ikut terbawa setiap dump/backup database.
 *
 * `down()` mengembalikan kolom nullable agar rollback tetap mungkin, tanpa
 * mengembalikan nilai lama (memang tidak ada yang perlu dipulihkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinic_payment_accounts')
            || ! Schema::hasColumn('clinic_payment_accounts', 'server_key')) {
            return;
        }

        Schema::table('clinic_payment_accounts', function (Blueprint $table) {
            $table->dropColumn('server_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clinic_payment_accounts')
            || Schema::hasColumn('clinic_payment_accounts', 'server_key')) {
            return;
        }

        Schema::table('clinic_payment_accounts', function (Blueprint $table) {
            $table->text('server_key')->nullable()->after('merchant_id');
        });
    }
};
