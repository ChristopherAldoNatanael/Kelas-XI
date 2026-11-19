<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan layanan yang belum ada di katalog klinik.
 *
 * Satu tabel (bukan request + offer terpisah) karena satu permintaan hanya
 * punya satu penawaran aktif; penawaran diperbarui di tempat dan riwayat
 * perubahan tetap terekam di audit log. `status` sengaja terpisah dari
 * appointment/payment: ini pengajuan, bukan transaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            // Dokter penanggung jawab evaluasi medis (ditugaskan admin).
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('medical_record_id')->nullable()->constrained('medical_records')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->string('need_type', 40);
            $table->text('complaint');
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time', 5)->nullable();

            $table->string('status', 30)->default('submitted');

            $table->text('admin_notes')->nullable();
            $table->text('doctor_recommendation')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->boolean('needs_follow_up')->default(false);

            // Penawaran klinik. `offer_price_type` membedakan harga final dan
            // estimasi supaya aplikasi tidak menampilkan estimasi sebagai
            // tagihan pasti.
            $table->foreignId('offer_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('offer_service_name')->nullable();
            $table->text('offer_description')->nullable();
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->string('offer_price_type', 10)->default('final');
            $table->unsignedInteger('offer_duration_minutes')->nullable();
            $table->foreignId('offer_doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('offer_schedule_date')->nullable();
            $table->string('offer_schedule_time', 5)->nullable();
            $table->text('offer_notes')->nullable();
            $table->dateTime('offer_valid_until')->nullable();
            $table->timestamp('offered_at')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('owner_response_note')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['clinic_id', 'doctor_id', 'status'], 'service_requests_doctor_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
