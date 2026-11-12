<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Identitas klinik pada API pasien (MULTI-CLINIC).
 *
 * BUG YANG DIKUNCI: aplikasi Android mencetak struk dari preferensi "klinik
 * aktif". Untuk pasien yang berobat di lebih dari satu klinik, nama klinik
 * pada struk bisa milik klinik yang SALAH. Karena itu server wajib mengirim
 * klinik milik booking/rekam medis itu sendiri.
 *
 * Catatan jujur: `bookings.clinic_id` NOT NULL, jadi booking tanpa klinik
 * mustahil dan tidak diuji di sini. Aplikasi tetap punya tampilan cadangan
 * bila `clinic` null (mis. klinik tidak lagi terbaca).
 */
class ApiClinicIdentityTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $patient;
    private Pet $pet;
    private Booking $bookingA;
    private Booking $bookingB;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Identitas A ' . $suffix,
            'slug' => 'identitas-a-' . $suffix,
            'address' => 'Jl. Sepuluh No. 10',
            'phone' => '0210000001',
            'primary_color' => '#0EA5A5',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Identitas B ' . $suffix,
            'slug' => 'identitas-b-' . $suffix,
            'address' => 'Jl. Dua Puluh No. 20',
            'phone' => '0210000002',
            'primary_color' => '#F59E0B',
            'is_active' => true,
        ]);

        $this->patient = User::create([
            'name' => 'Pasien Dua Klinik',
            'email' => 'dua-klinik-' . $suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => null,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'Mochi',
            'species' => 'Kucing',
            'breed' => 'Domestik',
            'gender' => 'male',
            'birth_date' => '2022-01-01',
        ]);

        $this->bookingA = $this->makeBooking($this->clinicA, '10:00');
        $this->bookingB = $this->makeBooking($this->clinicB, '15:00');
    }

    private function makeBooking(Clinic $clinic, string $time): Booking
    {
        $doctor = Doctor::create([
            'clinic_id' => $clinic->id,
            'name' => 'drh. ' . $clinic->name,
            'specialization' => 'Umum',
            'is_active' => true,
            // Kolom wajib pada tabel doctors (tanpa default di MySQL).
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);
        $service = Service::create([
            'clinic_id' => $clinic->id,
            'name' => 'Konsultasi ' . $clinic->slug,
            'price' => 150000,
            'duration' => 30,
            'is_active' => true,
        ]);

        return Booking::create([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'clinic_id' => $clinic->id,
            'booking_date' => Carbon::today()->addDays(2)->toDateString(),
            'booking_time' => $time,
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_status' => 'paid',
            'total_amount' => 150000,
            'paid_amount' => 150000,
        ]);
    }

    private function headers(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('clinic-identity')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_each_booking_reports_its_own_clinic(): void
    {
        $response = $this->withHeaders($this->headers($this->patient))
            ->getJson('/api/bookings');

        $response->assertOk();

        $items = collect($response->json('data'))->keyBy('id');

        $this->assertSame(
            $this->clinicA->name,
            $items[$this->bookingA->id]['clinic']['name'] ?? null,
            'Booking klinik A harus membawa klinik A'
        );
        $this->assertSame(
            $this->clinicB->name,
            $items[$this->bookingB->id]['clinic']['name'] ?? null,
            'Booking klinik B harus membawa klinik B'
        );
        $this->assertSame(
            'Jl. Sepuluh No. 10',
            $items[$this->bookingA->id]['clinic']['address'] ?? null,
            'Alamat klinik pada booking harus alamat kliniknya sendiri'
        );
        $this->assertSame(
            '#F59E0B',
            $items[$this->bookingB->id]['clinic']['primary_color'] ?? null
        );
    }

    public function test_medical_record_reports_its_own_clinic(): void
    {
        MedicalRecord::create([
            'booking_id' => $this->bookingA->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->bookingA->doctor_id,
            'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'Dermatitis ringan',
            'treatment' => 'Pembersihan area',
            'cost' => 150000,
            'total_medical_cost' => 150000,
            'extra_payment_status' => 'paid',
            'extra_payment_amount' => 0,
            'extra_payment_paid_amount' => 0,
        ]);

        $response = $this->withHeaders($this->headers($this->patient))
            ->getJson('/api/medical-records');

        $response->assertOk();

        $record = collect($response->json('data'))->firstWhere('booking_id', $this->bookingA->id);
        $this->assertNotNull($record, 'Rekam medis booking A harus ada di respons');
        $this->assertSame($this->clinicA->name, $record['clinic']['name'] ?? null);
        $this->assertSame('Jl. Sepuluh No. 10', $record['clinic']['address'] ?? null);
        $this->assertSame('#0EA5A5', $record['clinic']['primary_color'] ?? null);
    }

    public function test_medical_record_of_clinic_b_never_reports_clinic_a(): void
    {
        MedicalRecord::create([
            'booking_id' => $this->bookingB->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->bookingB->doctor_id,
            'clinic_id' => $this->clinicB->id,
            'diagnosis' => 'Vaksinasi',
            'treatment' => 'Vaksin rabies',
            'cost' => 150000,
            'total_medical_cost' => 150000,
            'extra_payment_status' => 'paid',
            'extra_payment_amount' => 0,
            'extra_payment_paid_amount' => 0,
        ]);

        $response = $this->withHeaders($this->headers($this->patient))
            ->getJson('/api/medical-records');

        $record = collect($response->json('data'))->firstWhere('booking_id', $this->bookingB->id);

        $this->assertSame($this->clinicB->name, $record['clinic']['name'] ?? null);
        $this->assertNotSame($this->clinicA->name, $record['clinic']['name'] ?? null);
    }
}
