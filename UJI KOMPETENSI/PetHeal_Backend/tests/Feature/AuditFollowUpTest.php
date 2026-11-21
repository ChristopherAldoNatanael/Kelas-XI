<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\ProductOrder;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\ClinicDeletionService;
use App\Services\FCMService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Test regresi untuk temuan audit backend (F-01 … F-11).
 *
 * Satu file, satu tujuan: memastikan perbaikan audit tidak kembali rusak.
 * Setiap test menamai temuannya supaya pesan gagal langsung menunjuk sumber
 * masalah. Test ini menambah cakupan jalur API yang sebelumnya paling tipis.
 */
class AuditFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private string $suffix;
    private Clinic $clinic;
    private User $admin;
    private User $patient;
    private Doctor $doctor;
    private Service $service;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->suffix = Str::random(6);

        $this->clinic = Clinic::create([
            'name' => 'Audit Clinic ' . $this->suffix,
            'slug' => 'audit-' . strtolower($this->suffix),
            'address' => 'Jl. Audit 1',
            'is_active' => true,
            'opening_time' => '07:00',
            'closing_time' => '21:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'primary_color' => '#18C964',
        ]);

        $makeUser = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag . ' ' . $this->suffix,
            'email' => strtolower($tag) . '-' . $this->suffix . '@audit.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'clinic_id' => $clinicId,
        ]);

        $this->admin = $makeUser('admin', 'clinic_admin', $this->clinic->id);
        // Pasien global: akun lintas klinik, `users.clinic_id` = NULL.
        $this->patient = $makeUser('patient', 'user', null);

        $this->doctor = Doctor::create([
            'name' => 'dr Audit ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->service = Service::create([
            'name' => 'Konsultasi Audit ' . $this->suffix,
            'price' => 150000,
            'duration' => 30,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'Audit Pet ' . $this->suffix,
            'species' => 'Dog',
            'gender' => 'Male',
        ]);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->createToken('audit-test')->plainTextToken,
            'Accept' => 'application/json',
        ]);
    }

    private function makeBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'clinic_id' => $this->clinic->id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_status' => 'pending',
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
        ], $attributes));
    }

    // ── F-01: pasien global harus terlihat oleh kliniknya ──────────────────

    public function test_f01_clinic_users_page_lists_global_patient_with_transaction(): void
    {
        $this->makeBooking();

        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee($this->patient->name, false);
    }

    public function test_f01_global_patient_without_transaction_is_not_listed(): void
    {
        // Tanpa booking/rekam medis di klinik ini, pasien bukan pasien klinik
        // tersebut — daftar akun tidak boleh membocorkan seluruh akun global.
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee($this->patient->name, false);
    }

    public function test_f01_clinic_can_open_global_patient_detail_but_only_its_own_bookings(): void
    {
        $this->makeBooking();

        $otherClinic = Clinic::create([
            'name' => 'Audit Clinic Lain ' . $this->suffix,
            'slug' => 'audit-lain-' . strtolower($this->suffix),
            'address' => 'Jl. Lain 2',
            'is_active' => true,
            'primary_color' => '#18C964',
        ]);
        $otherDoctor = Doctor::create([
            'name' => 'dr Lain ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $otherClinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        Booking::create([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $otherDoctor->id,
            'clinic_id' => $otherClinic->id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'booking_time' => '10:00:00',
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_status' => 'pending',
            'total_amount' => 200000,
            'remaining_amount' => 200000,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/users/' . $this->patient->id)
            ->assertOk()
            ->assertDontSee('dr Lain ' . $this->suffix, false);
    }

    public function test_f01_dashboard_counts_patient_accounts_not_pets(): void
    {
        // Satu pasien dengan dua hewan + satu booking di klinik ini.
        Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'Audit Pet Dua ' . $this->suffix,
            'species' => 'Cat',
            'gender' => 'Female',
        ]);
        $this->makeBooking();

        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertOk();

        $this->assertSame(
            1,
            (int) $response->viewData('totalPatients'),
            'Satu pemilik dengan dua hewan tetap dihitung satu pasien.'
        );
    }

    // ── F-12: satu definisi "pendapatan diterima" di dashboard ────────────

    public function test_f12_income_total_combines_booking_and_petshop_less_refund(): void
    {
        // Booking dilunasi penuh: 150.000 masuk.
        $this->makeBooking([
            'payment_status' => 'paid',
            'paid_amount' => 150000,
            'remaining_amount' => 0,
        ]);
        // Pesanan Pet Shop selesai: 50.000 masuk.
        ProductOrder::create([
            'order_number' => 'ORD-' . strtoupper($this->suffix),
            'user_id' => $this->patient->id,
            'clinic_id' => $this->clinic->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'subtotal' => 50000,
            'discount_total' => 0,
            'total_amount' => 50000,
            'currency' => 'IDR',
            'pickup_clinic_name' => $this->clinic->name,
            'pickup_clinic_address' => $this->clinic->address,
            'pickup_clinic_phone' => '021000000',
            'customer_name' => $this->patient->name,
            'customer_phone' => '081200000000',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertOk();

        $this->assertSame(
            200000,
            (int) $response->viewData('incomeTotal'),
            'Pendapatan diterima = booking + tindakan + Pet Shop selesai - refund.'
        );
        // Rincian: booking, biaya tindakan, Pet Shop — dalam urutan tetap.
        $breakdown = $response->viewData('incomeBreakdown');
        $this->assertSame(150000, (int) ($breakdown[0]['amount'] ?? 0), 'Booking harus sudah dikurangi refund.');
        $this->assertSame(0, (int) ($breakdown[1]['amount'] ?? -1), 'Belum ada biaya tindakan pada skenario ini.');
        $this->assertSame(50000, (int) ($breakdown[2]['amount'] ?? -1), 'Pet Shop selesai ikut dihitung.');
    }

    // ── F-03 + F-04: pembatalan melepas nomor antrian, dengan lock ─────────

    public function test_f03_patient_cancel_releases_queue_number_and_is_not_called(): void
    {
        $booking = $this->makeBooking([
            'booking_date' => Carbon::today()->toDateString(),
            'queue_number' => 1,
            'queue_date' => Carbon::today()->toDateString(),
            'queue_status' => 'waiting',
        ]);

        $this->apiAs($this->patient)
            ->postJson('/api/bookings/' . $booking->id . '/cancel', ['reason' => 'Berubah rencana'])
            ->assertOk();

        $fresh = $booking->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('no_show', $fresh->queue_status, 'Nomor antrian harus dilepas saat booking dibatalkan.');
        $this->assertStringContainsString('dibatalkan', (string) $fresh->queue_note);

        $this->assertNull(
            app(QueueService::class)->callNext($this->clinic->id),
            'Pasien yang dibatalkan tidak boleh dipanggil petugas.'
        );
    }

    // ── F-06: notifikasi menyimpan klinik yang memicunya ───────────────────

    public function test_f06_notification_history_keeps_the_booking_clinic(): void
    {
        app(FCMService::class)->sendBookingStatusUpdate(
            $this->patient->id,
            $this->pet->name,
            'confirmed',
            Carbon::tomorrow()->toDateString(),
            null,
            $this->pet->id,
            $this->doctor->id,
            $this->clinic->id
        );

        $notification = Notification::where('user_id', $this->patient->id)->latest('id')->first();

        $this->assertNotNull($notification, 'Riwayat notifikasi harus tetap tersimpan walau tanpa token perangkat.');
        $this->assertSame($this->clinic->id, (int) $notification->clinic_id);
    }

    // ── F-07: klinik dengan riwayat tidak boleh dihapus permanen ───────────

    public function test_f07_clinic_with_history_cannot_be_deleted_without_explicit_force(): void
    {
        $booking = $this->makeBooking();
        MedicalRecord::create([
            'booking_id' => $booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'subjective' => 'Sehat',
            'objective' => 'Normal',
            'assessment' => 'Sehat',
            'plan' => 'Kontrol',
            'diagnosis' => 'Sehat',
            'treatment' => 'Observasi',
            'cost' => 150000,
            'treatment_cost' => 0,
            'medicine_cost' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('masih menyimpan riwayat');

        app(ClinicDeletionService::class)->delete($this->clinic);
    }

    // ── F-09: daftar booking bisa diminta halaman berikutnya ───────────────

    public function test_f09_bookings_api_supports_page_parameter(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeBooking();
        }

        $first = $this->apiAs($this->patient)->getJson('/api/bookings?page=1');
        $first->assertOk();
        $this->assertSame(1, $first->json('pagination.current_page'));
        $this->assertGreaterThanOrEqual(2, $first->json('pagination.last_page'));

        $second = $this->apiAs($this->patient)->getJson('/api/bookings?page=2');
        $second->assertOk();
        $this->assertSame(2, $second->json('pagination.current_page'));
        $this->assertNotEmpty($second->json('data'), 'Halaman kedua harus berisi data, bukan kosong.');
    }

    // ── F-10: slot yang sudah lewat ditolak server ─────────────────────────

    public function test_f10_past_slot_today_is_rejected_by_the_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 15:00:00'));

        $this->apiAs($this->patient)->postJson('/api/bookings', [
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'booking_date' => '2026-11-20',
            'booking_time' => '09:00',
            'payment_type' => 'full',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(
            0,
            Booking::where('user_id', $this->patient->id)->count(),
            'Booking untuk jam yang sudah lewat tidak boleh tersimpan.'
        );

        // Slot yang masih akan datang tetap diterima.
        $this->apiAs($this->patient)->postJson('/api/bookings', [
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'booking_date' => '2026-11-20',
            'booking_time' => '16:00',
            'payment_type' => 'full',
        ])->assertStatus(201);

        Carbon::setTestNow();
    }

    // ── F-11: timezone domain = WIB ────────────────────────────────────────

    public function test_f11_application_timezone_is_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame(
            Carbon::now('Asia/Jakarta')->toDateString(),
            today()->toDateString(),
            'today() harus mengikuti tanggal WIB, bukan UTC.'
        );
    }
}
