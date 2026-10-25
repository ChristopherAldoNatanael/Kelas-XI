<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorScheduleException;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Isolasi portal dokter antar klinik.
 *
 * Dua klinik, dua dokter, dua pemilik hewan. Dokter A tidak boleh melihat atau
 * mengubah booking, rekam medis, jadwal, maupun profil milik dokter B — bahkan
 * bila ID-nya ditebak. Semua query portal memakai `doctor_id` + `clinic_id`
 * milik sesi aktif, jadi akses lintas dokter harus berakhir 404.
 */
class DoctorPortalIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $doctorUserA;
    private User $doctorUserB;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private User $ownerA;
    private User $ownerB;
    private Pet $petA;
    private Pet $petB;
    private Service $serviceA;
    private Service $serviceB;
    private Booking $bookingA;
    private Booking $bookingB;
    private MedicalRecord $recordA;
    private MedicalRecord $recordB;
    private DoctorScheduleException $exceptionA;
    private DoctorScheduleException $exceptionB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'ISO A ' . $this->suffix,
            'slug' => 'iso-a-' . $this->suffix,
            'address' => 'Jl. Iso A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'ISO B ' . $this->suffix,
            'slug' => 'iso-b-' . $this->suffix,
            'address' => 'Jl. Iso B',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, int $clinicId, string $role = 'user') {
            return User::create([
                'name' => $tag . ' ' . $this->suffix,
                'email' => $tag . $this->suffix . '@iso.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $clinicId,
            ]);
        };

        $this->doctorUserA = $mkUser('dr-a', $this->clinicA->id, 'doctor');
        $this->doctorUserB = $mkUser('dr-b', $this->clinicB->id, 'doctor');
        $this->ownerA = $mkUser('owner-a', $this->clinicA->id);
        $this->ownerB = $mkUser('owner-b', $this->clinicB->id);

        $mkDoctor = function (User $user, Clinic $clinic, string $name): Doctor {
            return Doctor::create([
                'user_id' => $user->id,
                'name' => $name,
                'specialization' => 'Umum',
                'email' => $user->email,
                'phone' => '081200000000',
                'is_active' => true,
                'clinic_id' => $clinic->id,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
        };
        $this->doctorA = $mkDoctor($this->doctorUserA, $this->clinicA, 'Dr Iso A ' . $this->suffix);
        $this->doctorB = $mkDoctor($this->doctorUserB, $this->clinicB, 'Dr Iso B ' . $this->suffix);

        $mkService = fn (Clinic $clinic, string $tag) => Service::create([
            'name' => 'Layanan ' . $tag . ' ' . $this->suffix,
            'price' => 75000,
            'is_active' => true,
            'clinic_id' => $clinic->id,
        ]);
        $this->serviceA = $mkService($this->clinicA, 'A');
        $this->serviceB = $mkService($this->clinicB, 'B');

        $this->petA = Pet::create([
            'user_id' => $this->ownerA->id,
            'name' => 'PetIsoA' . $this->suffix,
            'species' => 'Dog',
            'gender' => 'male',
        ]);
        $this->petB = Pet::create([
            'user_id' => $this->ownerB->id,
            'name' => 'PetIsoB' . $this->suffix,
            'species' => 'Cat',
            'gender' => 'female',
        ]);

        $mkBooking = fn (User $owner, Pet $pet, Doctor $doctor, Service $service, Clinic $clinic) => Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'booking_date' => Carbon::today()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_type' => 'full',
            'total_amount' => 75000,
            'paid_amount' => 0,
            'remaining_amount' => 75000,
            'clinic_id' => $clinic->id,
        ]);
        $this->bookingA = $mkBooking($this->ownerA, $this->petA, $this->doctorA, $this->serviceA, $this->clinicA);
        $this->bookingB = $mkBooking($this->ownerB, $this->petB, $this->doctorB, $this->serviceB, $this->clinicB);

        $this->recordA = MedicalRecord::create([
            'booking_id' => $this->bookingA->id,
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'DiagnosisA-' . $this->suffix,
            'treatment' => 'TreatmentA',
        ]);
        $this->recordB = MedicalRecord::create([
            'booking_id' => $this->bookingB->id,
            'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id,
            'clinic_id' => $this->clinicB->id,
            'diagnosis' => 'DiagnosisB-' . $this->suffix,
            'treatment' => 'TreatmentB',
        ]);

        $this->exceptionA = DoctorScheduleException::create([
            'doctor_id' => $this->doctorA->id,
            'clinic_id' => $this->clinicA->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'is_closed' => true,
            'reason' => 'Cuti A',
            'created_by' => $this->doctorUserA->id,
        ]);
        $this->exceptionB = DoctorScheduleException::create([
            'doctor_id' => $this->doctorB->id,
            'clinic_id' => $this->clinicB->id,
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'is_closed' => true,
            'reason' => 'Cuti B',
            'created_by' => $this->doctorUserB->id,
        ]);
    }

    public function test_dashboard_and_list_only_expose_own_clinic_data(): void
    {
        $dashboard = $this->actingAs($this->doctorUserA)->get('/doctor');

        $dashboard->assertOk();
        $dashboard->assertSee($this->petA->name);
        $dashboard->assertDontSee($this->petB->name);

        $todayBookings = $dashboard->viewData('todayBookings');
        $this->assertCount(1, $todayBookings);
        $this->assertSame($this->bookingA->id, $todayBookings->first()->id);

        $this->actingAs($this->doctorUserA)->get('/doctor/bookings')
            ->assertOk()
            ->assertSee($this->petA->name)
            ->assertDontSee($this->petB->name);

        $this->actingAs($this->doctorUserA)->get('/doctor/medical-records')
            ->assertOk()
            ->assertSee('DiagnosisA-' . $this->suffix)
            ->assertDontSee('DiagnosisB-' . $this->suffix);
    }

    public function test_cross_doctor_booking_access_is_denied(): void
    {
        $asA = fn () => $this->actingAs($this->doctorUserA);

        $asA()->get('/doctor/bookings/' . $this->bookingB->id)->assertNotFound();
        $asA()->post('/doctor/bookings/' . $this->bookingB->id . '/confirm')->assertNotFound();
        $asA()->post('/doctor/bookings/' . $this->bookingB->id . '/complete')->assertNotFound();
        $asA()->post('/doctor/bookings/' . $this->bookingB->id . '/cancel', [
            'reason' => 'Niat jahat',
        ])->assertNotFound();

        $fresh = $this->bookingB->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->cancellation_reason);

        // Booking sendiri tetap bisa diakses.
        $asA()->get('/doctor/bookings/' . $this->bookingA->id)->assertOk();
    }

    public function test_cross_doctor_medical_records_are_denied(): void
    {
        $asA = fn () => $this->actingAs($this->doctorUserA);

        $asA()->get('/doctor/medical-records/' . $this->recordB->id)->assertNotFound();
        $asA()->get('/doctor/medical-records/' . $this->recordB->id . '/edit')->assertNotFound();
        $asA()->get('/doctor/medical-records/create/' . $this->bookingB->id)->assertNotFound();

        $asA()->put('/doctor/medical-records/' . $this->recordB->id, [
            'diagnosis' => 'Hijacked',
            'treatment' => 'Hijacked',
        ])->assertNotFound();
        $this->assertSame('DiagnosisB-' . $this->suffix, $this->recordB->fresh()->diagnosis);

        // Menyimpan rekam medis dengan booking_id milik dokter/klinik lain
        // harus ditolak validasi, bukan membuat rekam medis baru.
        $before = MedicalRecord::count();
        $asA()->post('/doctor/medical-records', [
            'booking_id' => $this->bookingB->id,
            'diagnosis' => 'Hijacked',
            'treatment' => 'Hijacked',
        ])->assertSessionHasErrors('booking_id');
        $this->assertSame($before, MedicalRecord::count());

        $asA()->get('/doctor/medical-records/' . $this->recordA->id)->assertOk();
    }

    public function test_cross_doctor_schedule_exception_is_denied(): void
    {
        $this->actingAs($this->doctorUserA)
            ->delete('/doctor/schedule/' . $this->exceptionB->id)
            ->assertNotFound();

        $this->assertNotNull(DoctorScheduleException::find($this->exceptionB->id));

        $this->actingAs($this->doctorUserA)
            ->delete('/doctor/schedule/' . $this->exceptionA->id)
            ->assertRedirect();

        $this->assertNull(DoctorScheduleException::find($this->exceptionA->id));
    }

    public function test_profile_update_is_self_scoped_and_ignores_privileged_fields(): void
    {
        $originalDoctorB = $this->doctorB->only(['name', 'specialization', 'email', 'clinic_id']);

        $this->actingAs($this->doctorUserA)->put('/doctor/profile', [
            'name' => 'Nama Baru A',
            'phone' => '089900000000',
            // Field di bawah tidak boleh berubah lewat portal dokter.
            'email' => 'attacker@iso.test',
            'specialization' => 'Spesialis Palsu',
            'clinic_id' => $this->clinicB->id,
            'available_days' => ['sunday'],
            'is_active' => false,
        ])->assertRedirect();

        $this->doctorA->refresh();
        $this->doctorUserA->refresh();

        $this->assertSame('Nama Baru A', $this->doctorA->name);
        $this->assertSame('Nama Baru A', $this->doctorUserA->name);
        $this->assertSame('089900000000', $this->doctorA->phone);
        $this->assertSame($this->clinicA->id, $this->doctorA->clinic_id);
        $this->assertSame('Umum', $this->doctorA->specialization);
        $this->assertSame($this->clinicA->id, $this->doctorUserA->clinic_id);
        $this->assertNotSame('attacker@iso.test', $this->doctorUserA->email);

        $this->assertSame($originalDoctorB['name'], $this->doctorB->fresh()->name);
        $this->assertSame($originalDoctorB['clinic_id'], $this->doctorB->fresh()->clinic_id);
    }

    public function test_password_change_only_affects_own_account(): void
    {
        Mail::fake();
        $otherHash = $this->doctorUserB->password;

        // Langkah 1: kata sandi baru + minta kode. Tidak ada kata sandi lama.
        $this->actingAs($this->doctorUserA)->post('/doctor/profile/password/otp', [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password123', $this->doctorUserA->fresh()->password));

        $code = $this->sentOtpCode();

        // Langkah 2: kode diverifikasi, baru kata sandi tersimpan.
        $this->actingAs($this->doctorUserA)->put('/doctor/profile/password', [
            'code' => str_split($code),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $this->doctorUserA->fresh()->password));
        $this->assertSame($otherHash, $this->doctorUserB->fresh()->password);

        // Tidak ada parameter target user: permintaan tanpa kode valid tidak
        // bisa mengubah akun lain.
        $this->actingAs($this->doctorUserA)->put('/doctor/profile/password', [
            'user_id' => $this->doctorUserB->id,
            'password' => 'another-pass-123',
            'password_confirmation' => 'another-pass-123',
        ])->assertSessionHasErrors('code');

        $this->assertSame($otherHash, $this->doctorUserB->fresh()->password);
    }

    /** Kode OTP terakhir yang dikirim ke email (Mail sudah di-fake). */
    private function sentOtpCode(): string
    {
        $code = null;

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->assertNotNull($code);

        return $code;
    }

    public function test_mismatched_account_clinic_is_rejected_at_the_portal_gate(): void
    {
        // Simulasi data rusak/manipulasi: akun clinic A ditautkan ke profil
        // dokter clinic B. Middleware harus menolak sebelum controller jalan.
        $this->doctorUserA->update(['clinic_id' => $this->clinicB->id]);

        $this->actingAs($this->doctorUserA)
            ->get('/doctor')
            ->assertRedirect(route('doctor.login'));

        $this->assertGuest();
        $this->assertNull(auth()->user());
    }

    /** Halaman portal harus merender 200 — guard regresi Blade (terutama setelah redesign). */
    public function test_portal_pages_render(): void
    {
        $this->actingAs($this->doctorUserA)
            ->get('/doctor/schedule')
            ->assertOk()
            ->assertSee('Jadwal & Cuti');

        $this->actingAs($this->doctorUserA)
            ->get('/doctor/profile')
            ->assertOk()
            ->assertSee('Profil Saya');
    }
}
