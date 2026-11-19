<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status token perangkat.
 *
 * Token FCM bisa jadi tidak valid (aplikasi di-uninstall, token dirotasi,
 * perangkat direset). Token seperti itu tidak boleh terus dikirimi notifikasi
 * — setiap percobaan kirim hanya menambah kuota dan log error.
 *
 * Kolom ini nullable-friendly: token lama dianggap `active` sampai FCM
 * memberi tahu bahwa token tersebut sudah tidak berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('device_type');
            $table->timestamp('last_used_at')->nullable()->after('status');
        });

        // Pengiriman notifikasi selalu menyaring per status + user.
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_device_tokens_user_status');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_device_tokens_user_status');
            $table->dropColumn(['status', 'last_used_at']);
        });
    }
};
