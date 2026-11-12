<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guard integritas data & uang.
 *
 * Latar belakang: FK `bookings.doctor_id` dan `medical_records.doctor_id`
 * memakai onDelete('cascade'), sehingga satu klik "hapus dokter" bisa
 * memusnahkan riwayat medis dan finansial pasien. Selain itu agregat
 * "total collected" menjumlahkan booking yang sudah dibatalkan, dan upload
 * foto hewan menerima file apa pun. Test di sini mengunci perilaku benar.
 */
class DataIntegrityGuardsTest extends TestCase
{
    use DatabaseTransactions;

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
        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Guard Clinic ' . $this->suffix,
            'slug' => 'guard-' . $this->suffix,
            'address' => 'Jl. Guard No. 1',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, string $role) {
            return User::create([
                'name' => $tag,
                'email' => $tag . $this->suffix . '@guard.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $role === 'user' ? null : $this->clinic->id,
            ]);
        };

        $this->admin = $mkUser('gadmin', 'clinic_admin');
        $this->patient = $mkUser('gpatient', 'user');

        $this->doctor = Doctor::create([
            'name' => 'Dr Guard ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->service = Service::create([
            'name' => 'Konsultasi Guard ' . $this->suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'GuardPet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'clinic_id' => $this->clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
        ], $overrides));
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('guard-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Clinic-Slug' => $this->clinic->slug,
        ]);
    }

    // ---------- 1. Hapus dokter tidak boleh memusnahkan riwayat ----------

    public function test_deleting_doctor_with_history_keeps_records_and_deactivates_profile(): void
    {
        $booking = $this->makeBooking(['status' => 'completed']);
        $record = MedicalRecord::create([
            'booking_id' => $booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Dermatitis',
            'treatment' => 'Salep',
            'cost' => 150000,
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/doctors/' . $this->doctor->id)
            ->assertRedirect(route('admin.doctors.index'));

        // Profil tetap ada tetapi nonaktif, dan riwayat klinis utuh.
        $this->assertDatabaseHas('doctors', [
            'id' => $this->doctor->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('medical_records', ['id' => $record->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_deleting_doctor_without_history_removes_profile(): void
    {
        $spare = Doctor::create([
            'name' => 'Dr Tanpa Riwayat ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/doctors/' . $spare->id)
            ->assertRedirect(route('admin.doctors.index'));

        $this->assertDatabaseMissing('doctors', ['id' => $spare->id]);
    }

    // ---------- 2. Booking batal bukan pendapatan ----------

    public function test_cancelled_booking_counts_until_refunded_then_nets_to_zero(): void
    {
        // Booking lain di DB lokal mungkin sudah punya pembayaran. Test ini
        // membandingkan angka SEBELUM dan SESUDAH, jadi tidak bergantung pada
        // bersih/tidaknya database.
        $before = $this->collectedTotal();

        $booking = $this->makeBooking([
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);

        // Uang yang sudah masuk tetap dihitung sampai benar-benar dikembalikan.
        // Dulu status `cancelled` dikeluarkan dari paid_amount LALU refund
        // dikurangkan lagi: refund penuh menghasilkan angka negatif.
        $this->assertSame(
            $before + 100000.0,
            $this->collectedTotal(),
            'Uang booking batal hilang dari laporan padahal belum direfund.'
        );

        app(RefundService::class)->record($booking, 100000, 'transfer', null, 'Refund penuh');

        $this->assertSame(
            $before,
            $this->collectedTotal(),
            'Refund penuh harus mengembalikan angka tepat ke posisi semula, bukan minus.'
        );
    }

    public function test_confirmed_paid_booking_is_counted_as_collected(): void
    {
        $before = $this->collectedTotal();

        $this->makeBooking([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_amount' => 100000,
            'remaining_amount' => 0,
        ]);

        // Kontrol positif: memastikan agregat tidak "0 selamanya".
        $this->assertSame($before + 100000.0, $this->collectedTotal());
    }

    /**
     * Baca angka "terkumpul" lewat method yang dipakai halaman Pembayaran,
     * supaya test dan UI tidak punya dua definisi berbeda.
     */
    private function collectedTotal(): float
    {
        // Constructor butuh FCMService; bisa diselesaikan container.
        $controller = app(\App\Http\Controllers\Admin\PaymentController::class);
        $method = new \ReflectionMethod($controller, 'paymentStats');
        $method->setAccessible(true);

        $stats = $method->invoke($controller, Booking::query());

        return (float) $stats['total_collected'];
    }

    // ---------- 3. Upload foto hewan menolak berkas bukan gambar ----------

    public function test_pet_photo_upload_rejects_non_image_file(): void
    {
        $response = $this->apiAs($this->patient)->post('/api/pets', [
            'name' => 'FotoPalsu',
            'species' => 'Dog',
            'photo' => UploadedFile::fake()->create('catatan.pdf', 20, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['photo']);
        $this->assertDatabaseMissing('pets', ['name' => 'FotoPalsu']);
    }
}
