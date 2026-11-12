<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrian harian per klinik.
 *
 * KEPUTUSAN PENTING: kolom antrian DIPISAH dari `status` booking.
 * `status` (pending/confirmed/completed/cancelled) mengurus administrasi
 * (terkonfirmasi, lunas, selesai), sedangkan antrian mengurus kehadiran
 * fisik pasien di klinik. Menggabungkan keduanya akan memaksa mengubah enum
 * status yang dipakai banyak tes dan laporan — risiko besar tanpa manfaat.
 *
 * Semua kolom nullable sehingga booking lama tidak perlu diisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nomor urut harian per klinik. NULL = pasien belum check-in.
            $table->unsignedInteger('queue_number')->nullable()->after('cancellation_reason');
            // Tanggal antrian. Dipisah dari booking_date karena pasien bisa
            // datang di hari lain (jadwal diundur), dan nomor antrian harus
            // tetap mengikuti hari ia benar-benar datang.
            $table->date('queue_date')->nullable()->after('queue_number');
            // waiting | called | in_service | done | no_show
            $table->string('queue_status', 20)->nullable()->after('queue_date');
            $table->timestamp('checked_in_at')->nullable()->after('queue_status');
            $table->timestamp('called_at')->nullable()->after('checked_in_at');
            $table->timestamp('queue_finished_at')->nullable()->after('called_at');

            // Satu nomor hanya boleh dipakai sekali per klinik per hari.
            $table->unique(['clinic_id', 'queue_date', 'queue_number'], 'bookings_queue_unique');
            $table->index(['clinic_id', 'queue_date', 'queue_status'], 'bookings_queue_board');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_queue_unique');
            $table->dropIndex('bookings_queue_board');
            $table->dropColumn([
                'queue_number',
                'queue_date',
                'queue_status',
                'checked_in_at',
                'called_at',
                'queue_finished_at',
            ]);
        });
    }
};
