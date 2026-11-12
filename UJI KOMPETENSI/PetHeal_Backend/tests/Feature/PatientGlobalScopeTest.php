<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AUDIT MULTI-KLINIK (2026-11-11): sisa asumsi "satu klinik" di sisi server.
 *
 * Pasien (`role=user`) adalah akun GLOBAL. `users.clinic_id` pada akun pasien
 * adalah data lama dan TIDAK BOLEH dipakai sebagai filter otorisasi. Sebelum
 * perbaikan ini, pasien lama:
 *  - hanya melihat dokter klinik lamanya (katalog lintas klinik mati),
 *  - tidak melihat layanan klinik lain (perbandingan harga kosong),
 *  - mendapat 404 saat membuka dokter klinik lain (detail/slot/review),
 *  - dan tombol check-in memanggil method controller yang tidak ada (500).
 *
 * Semua perilaku itu dikunci di sini. Staf klinik tetap terkurung.
 */
class PatientGlobalScopeTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $legacyPatient;
    private Doctor $doctorB;
    private Service $serviceB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Global A ' . $suffix,
            'slug' => 'global-a-' . $suffix,
            'address' => 'Jl. Global A No. 1',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Global B ' . $suffix,
            'slug' => 'global-b-' . $suffix,
            'address' => 'Jl. Global B No. 2',
            'is_active' => true,
        ]);

        // Pasien LAMA: masih menyimpan clinic_id klinik A dari era satu-klinik.
        $this->legacyPatient = User::create([
            'name' => 'Pasien Lama ' . $suffix,
            'email' => 'lama-' . $suffix . '@patient-scope.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->doctorB = $this->makeDoctor($this->clinicB, 'drh. Global B');
        $this->makeDoctor($this->clinicA, 'drh. Global A');
        $this->serviceB = Service::create([
            'clinic_id' => $this->clinicB->id,
            'name' => 'Vaksin Rabies',
            'price' => 220000,
            'duration' => 30,
            'is_active' => true,
        ]);
        Service::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Konsultasi Umum',
            'price' => 120000,
            'duration' => 30,
            'is_active' => true,
        ]);
    }

    private function makeDoctor(Clinic $clinic, string $name): Doctor
    {
        return Doctor::create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'specialization' => 'Umum',
            'is_active' => true,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00',
            'end_time' => '20:00',
        ]);
    }

    private function headers(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('patient-scope')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_legacy_patient_sees_doctors_from_every_clinic(): void
    {
        $response = $this->withHeaders($this->headers($this->legacyPatient))
            ->getJson('/api/doctors');

        $response->assertOk();

        $clinicIds = collect($response->json('data'))->pluck('clinic_id')->unique();
        $this->assertTrue(
            $clinicIds->contains($this->clinicB->id),
            'Pasien lama tetap harus melihat dokter klinik B (akun global)'
        );
    }

    public function test_legacy_patient_can_open_another_clinic_doctor_detail(): void
    {
        $response = $this->withHeaders($this->headers($this->legacyPatient))
            ->getJson('/api/doctors/' . $this->doctorB->id);

        $response->assertOk();
        $this->assertSame($this->clinicB->name, $response->json('data.clinic.name'));
    }

    public function test_legacy_patient_sees_services_from_every_clinic(): void
    {
        $response = $this->withHeaders($this->headers($this->legacyPatient))
            ->getJson('/api/services');

        $response->assertOk();

        $clinicIds = collect($response->json('data'))->pluck('clinic_id')->unique();
        $this->assertTrue(
            $clinicIds->contains($this->clinicB->id),
            'Perbandingan harga butuh layanan dari klinik lain, bukan hanya klinik lama'
        );
    }

    public function test_doctor_without_reviews_reports_null_average_rating(): void
    {
        $response = $this->withHeaders($this->headers($this->legacyPatient))
            ->getJson('/api/doctors/' . $this->doctorB->id . '/reviews');

        $response->assertOk();
        $this->assertNull(
            $response->json('data.average_rating'),
            'Belum ada ulasan = null, bukan 0 (0 berarti dinilai 0/5)'
        );
    }

    public function test_patient_check_in_returns_daily_queue_number(): void
    {
        $pet = Pet::create([
            'user_id' => $this->legacyPatient->id,
            'name' => 'Mochi',
            'species' => 'Kucing',
            'breed' => 'Domestik',
            'gender' => 'male',
            'birth_date' => '2022-01-01',
        ]);

        $booking = Booking::create([
            'user_id' => $this->legacyPatient->id,
            'pet_id' => $pet->id,
            'doctor_id' => $this->doctorB->id,
            'service_id' => $this->serviceB->id,
            'clinic_id' => $this->clinicB->id,
            'booking_date' => Carbon::today()->toDateString(),
            'booking_time' => '10:00',
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'total_amount' => 220000,
            'paid_amount' => 220000,
        ]);

        $response = $this->withHeaders($this->headers($this->legacyPatient))
            ->postJson('/api/bookings/' . $booking->id . '/check-in');

        $response->assertOk();
        $this->assertSame(1, $response->json('queue_number'));
        $this->assertSame('A-01', $response->json('queue_label'));
        $this->assertSame(0, $response->json('queue_position'));

        // Idempoten: tekan dua kali tidak memberi nomor baru.
        $again = $this->withHeaders($this->headers($this->legacyPatient))
            ->postJson('/api/bookings/' . $booking->id . '/check-in');
        $again->assertOk();
        $this->assertSame(1, $again->json('queue_number'));

        // Daftar booking juga wajib membawa label & posisi — bukan hanya
        // respons check-in — supaya strip antrian bertahan setelah refresh.
        $list = $this->withHeaders($this->headers($this->legacyPatient))
            ->getJson('/api/bookings');
        $list->assertOk();

        $row = collect($list->json('data'))->firstWhere('id', $booking->id);
        $this->assertNotNull($row);
        $this->assertSame('A-01', $row['queue_label']);
        $this->assertSame(0, $row['queue_position']);
    }

    public function test_booking_of_another_patient_cannot_be_checked_in(): void
    {
        $other = User::create([
            'name' => 'Pasien Lain',
            'email' => 'lain-' . Str::random(6) . '@patient-scope.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => null,
        ]);
        $pet = Pet::create([
            'user_id' => $other->id,
            'name' => 'Boba',
            'species' => 'Anjing',
            'breed' => 'Mix',
            'gender' => 'female',
            'birth_date' => '2021-05-05',
        ]);
        $booking = Booking::create([
            'user_id' => $other->id,
            'pet_id' => $pet->id,
            'doctor_id' => $this->doctorB->id,
            'service_id' => $this->serviceB->id,
            'clinic_id' => $this->clinicB->id,
            'booking_date' => Carbon::today()->toDateString(),
            'booking_time' => '11:00',
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'total_amount' => 220000,
            'paid_amount' => 220000,
        ]);

        $this->withHeaders($this->headers($this->legacyPatient))
            ->postJson('/api/bookings/' . $booking->id . '/check-in')
            ->assertNotFound();
    }
}
