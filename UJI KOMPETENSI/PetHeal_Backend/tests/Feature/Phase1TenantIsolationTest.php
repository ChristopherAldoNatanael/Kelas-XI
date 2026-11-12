<?php

namespace Tests\Feature;

use App\Imports\ServicesImport;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 1 validation: critical security & data isolation fixes.
 *
 * Two tenants (Clinic A vs Clinic B) + null-clinic user + super_admin.
 * Uses DatabaseTransactions so the dev database is left untouched.
 */
class Phase1TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $userA;
    private User $userB;
    private User $nullUser;
    private User $superAdmin;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Service $serviceA;
    private Service $serviceB;
    private Pet $petA;
    private Pet $petB;
    private PaymentMethod $payMethod;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->suffix = Str::random(6);

        $this->clinicA = Clinic::create([
            'name' => 'Audit Clinic A ' . $this->suffix,
            'slug' => 'audit-a-' . strtolower($this->suffix),
            'address' => 'Jl. Audit A No. 1',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Audit Clinic B ' . $this->suffix,
            'slug' => 'audit-b-' . strtolower($this->suffix),
            'address' => 'Jl. Audit B No. 2',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, string $role, ?int $clinicId) {
            return User::create([
                'name' => $tag . ' ' . $this->suffix,
                'email' => strtolower($tag) . '-' . $this->suffix . '@audit.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $clinicId,
            ]);
        };

        $this->userA = $mkUser('userA', 'user', $this->clinicA->id);
        $this->userB = $mkUser('userB', 'user', $this->clinicB->id);
        $this->nullUser = $mkUser('nullUser', 'user', null);
        $this->superAdmin = $mkUser('superAdmin', 'super_admin', null);

        $mkDoctor = function (int $clinicId, string $tag) {
            return Doctor::create([
                'name' => 'Dr ' . $tag . ' ' . $this->suffix,
                'specialization' => 'Umum',
                'is_active' => true,
                'clinic_id' => $clinicId,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
        };
        $this->doctorA = $mkDoctor($this->clinicA->id, 'A');
        $this->doctorB = $mkDoctor($this->clinicB->id, 'B');

        $this->serviceA = Service::create([
            'name' => 'Konsultasi ' . $this->suffix, 'price' => 100000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
        $this->serviceB = Service::create([
            'name' => 'Vaksinasi ' . $this->suffix, 'price' => 200000,
            'is_active' => true, 'clinic_id' => $this->clinicB->id,
        ]);

        $this->petA = Pet::create([
            'user_id' => $this->userA->id, 'name' => 'PetA',
            'species' => 'Cat', 'gender' => 'male',
        ]);
        $this->petB = Pet::create([
            'user_id' => $this->userB->id, 'name' => 'PetB',
            'species' => 'Dog', 'gender' => 'female',
        ]);

        $this->payMethod = PaymentMethod::create([
            'name' => 'AuditPay ' . $this->suffix, 'type' => 'qris',
            'is_active' => true,
        ]);
    }

    private function apiAs(User $user, array $headers = [])
    {
        // Feature tests reuse one app instance across HTTP calls and guards
        // memoize the first user. Forget guards so each request re-resolves
        // the Bearer token (production boots a fresh app per request).
        auth()->forgetGuards();
        $token = $user->createToken('phase1-test')->plainTextToken;

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], $headers));
    }

    private function bookingDate(): string
    {
        return Carbon::tomorrow()->format('Y-m-d');
    }

    // ---------- D3: cross-clinic reads ----------
    //
    // MULTI-CLINIC (2026-11-11): pasien (`role=user`) adalah akun GLOBAL.
    // Doctor A dan doctor B di sini dibuat sebagai AKUN PASIEN yang masih
    // membawa `users.clinic_id` lama (data era satu-klinik). Dulu test ini
    // menuntut 404 untuk dokter klinik lain; aturan itu sudah sengaja
    // dicabut (docs §3, §11) karena membuat pasien lama tidak bisa berobat
    // di klinik lain. Yang diuji sekarang: pasien lintas klinik BOLEH
    // membaca, sedangkan staf tetap terkurung.

    public function test_patient_may_read_another_clinic_doctor(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/doctors/' . $this->doctorB->id)
            ->assertOk();

        $this->assertSame($this->clinicB->id, $res->json('data.clinic_id'));
    }

    public function test_patient_may_read_either_clinic_doctor(): void
    {
        $this->apiAs($this->userB)->getJson('/api/doctors/' . $this->doctorA->id)
            ->assertOk();
    }

    public function test_patient_catalog_covers_both_clinics(): void
    {
        $res = $this->apiAs($this->userA)->getJson('/api/doctors?limit=100')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($this->doctorA->id, $ids);
        $this->assertContains($this->doctorB->id, $ids);
    }

    public function test_patient_catalog_can_be_forced_to_one_clinic(): void
    {
        $res = $this->apiAs($this->userA)
            ->getJson('/api/doctors?limit=100&clinic_id=' . $this->clinicB->id)
            ->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($this->doctorB->id, $ids);
        $this->assertNotContains($this->doctorA->id, $ids);
    }

    public function test_patient_may_read_another_clinic_slots(): void
    {
        $this->apiAs($this->userA)
            ->getJson('/api/doctors/' . $this->doctorB->id . '/slots?date=' . $this->bookingDate())
            ->assertOk();
    }

    public function test_clinic_staff_cannot_read_another_clinic_doctor(): void
    {
        $staffA = User::create([
            'name' => 'staffA ' . $this->suffix,
            'email' => 'staffa-' . $this->suffix . '@audit.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->apiAs($staffA)->getJson('/api/doctors/' . $this->doctorB->id)
            ->assertStatus(404);
        $this->apiAs($staffA)
            ->getJson('/api/doctors/' . $this->doctorB->id . '/slots?date=' . $this->bookingDate())
            ->assertStatus(404);
    }

    // ---------- D3: cross-clinic booking writes ----------

    public function test_a_cannot_book_with_b_doctor(): void
    {
        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorB->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->bookingDate(),
            'booking_time' => '09:00',
        ])->assertStatus(422);
    }

    public function test_a_cannot_book_with_b_service(): void
    {
        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceB->id,
            'booking_date' => $this->bookingDate(),
            'booking_time' => '09:00',
        ])->assertStatus(422);
    }

    public function test_a_cannot_read_b_booking_by_id(): void
    {
        $bookingB = Booking::create([
            'user_id' => $this->userB->id, 'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id, 'service_id' => $this->serviceB->id,
            'booking_date' => $this->bookingDate(), 'booking_time' => '10:00',
            'status' => 'pending', 'payment_status' => 'pending',
            'clinic_id' => $this->clinicB->id,
        ]);

        $this->apiAs($this->userA)->getJson('/api/bookings/' . $bookingB->id)
            ->assertStatus(404);
    }

    public function test_a_cannot_review_b_doctor_with_someone_elses_booking(): void
    {
        // MULTI-CLINIC (2026-11-11): yang menjaga review lintas klinik bukan
        // lagi klinik pemilik akun, melainkan KEPEMILIKAN booking. Pasien A
        // boleh membuka dokter klinik B (akun global), tetapi tidak boleh
        // menilai memakai booking milik pasien B. Hasilnya 422 (validasi
        // eksistensi ter-scope), bukan 404.
        $bookingB = Booking::create([
            'user_id' => $this->userB->id, 'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id, 'service_id' => $this->serviceB->id,
            'booking_date' => $this->bookingDate(), 'booking_time' => '10:00',
            'status' => 'completed', 'payment_status' => 'paid',
            'clinic_id' => $this->clinicB->id,
        ]);

        $this->apiAs($this->userA)->postJson('/api/doctors/' . $this->doctorB->id . '/reviews', [
            'booking_id' => $bookingB->id, 'rating' => 5,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('doctor_reviews', [
            'booking_id' => $bookingB->id,
        ]);
    }

    // ---------- D1: null-clinic fail-closed ----------

    public function test_null_clinic_user_gets_no_unrestricted_access(): void
    {
        // MULTI-CLINIC DISCOVERY (2026-11-09): pasien tanpa klinik kini SAH.
        // Yang diuji di sini bukan lagi 403, melainkan batas aksesnya:
        // katalog lintas klinik boleh dibaca, tetapi data booking/rekam medis
        // tetap milik user sendiri dan booking lintas klinik tetap ditolak.
        $res = $this->apiAs($this->nullUser)->getJson('/api/doctors?limit=100');
        $res->assertOk();

        // Booking dengan dokter+layanan dari klinik yang berbeda tetap 422.
        $this->apiAs($this->nullUser)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceB->id,
            'booking_date' => $this->bookingDate(),
            'booking_time' => '09:00',
        ])->assertStatus(422);

        // Pet tetap harus milik pemanggil: pet klinik A tidak boleh dipakai.
        $this->apiAs($this->nullUser)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->bookingDate(),
            'booking_time' => '09:00',
        ])->assertStatus(422);

        // Booking sah dengan pet MILIK pasien global → klinik dari dokter.
        $ownPet = Pet::create([
            'user_id' => $this->nullUser->id, 'name' => 'NullPet',
            'species' => 'Cat', 'gender' => 'male',
        ]);
        $this->apiAs($this->nullUser)->postJson('/api/bookings', [
            'pet_id' => $ownPet->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->bookingDate(),
            'booking_time' => '09:00',
        ])->assertStatus(201);
    }

    public function test_null_clinic_user_can_still_delete_own_account(): void
    {
        // MULTI-CLINIC DISCOVERY: pasien tanpa klinik boleh membaca datanya
        // sendiri; hapus akun tetap self-scoped dan harus berhasil.
        $this->apiAs($this->nullUser)->getJson('/api/pets')->assertOk();

        $nullUserId = $this->nullUser->id;
        $this->apiAs($this->nullUser)->deleteJson('/api/auth/account')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(User::find($nullUserId));
    }

    public function test_null_clinic_user_can_delete_own_account_with_slug_header(): void
    {
        // PHASE 7 fix, exact app scenario: the Android client always sends
        // the stored X-Clinic-Slug header — that must not trap a clinic-less
        // user inside an account they cannot remove either.
        // Slug sisa sesi lama tidak boleh memblokir pasien global.
        $this->apiAs($this->nullUser, ['X-Clinic-Slug' => 'petheal-pusat'])
            ->getJson('/api/pets')->assertOk();

        $nullUserId = $this->nullUser->id;
        $this->apiAs($this->nullUser, ['X-Clinic-Slug' => 'petheal-pusat'])
            ->deleteJson('/api/auth/account')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(User::find($nullUserId));
    }

    public function test_null_clinic_admin_cannot_enter_web_panel(): void
    {
        $admin = User::create([
            'name' => 'Null Admin ' . $this->suffix,
            'email' => 'nulladmin-' . $this->suffix . '@audit.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => null,
        ]);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_bound_clinic_admin_can_enter_web_panel(): void
    {
        $admin = User::create([
            'name' => 'Bound Admin ' . $this->suffix,
            'email' => 'boundadmin-' . $this->suffix . '@audit.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);

        // Pakai token web (admin.auth memakai guard web + session), bukan
        // perilaku 403 dari middleware API yang diuji di file lain.
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    // ---------- super_admin preserved ----------

    public function test_super_admin_keeps_overview(): void
    {
        // PENTING: auth API lebih dulu. Bila `$this->actingAs()` (guard web)
        // dijalankan lebih dulu, state Sanctum yang menempel di instance test
        // membuat request Bearer berikutnya memakai user web tersebut —
        // artefak test, bukan perilaku produksi (satu proses per request).
        $res = $this->apiAs($this->superAdmin)->getJson('/api/doctors?limit=100')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->doctorA->id, $ids);
        $this->assertContains($this->doctorB->id, $ids);

        // Halaman web super admin tetap 200 (dibuktikan AdminConsoleSmokeTest
        // agar tidak bertabrakan dengan state guard di atas).
    }

    // ---------- D4: public services ----------

    public function test_public_services_require_explicit_slug(): void
    {
        // Anonim tetap wajib menyebut klinik (kontrak lama, tidak berubah).
        $this->getJson('/api/services')->assertStatus(422);
    }

    public function test_public_services_scoped_by_slug(): void
    {
        $res = $this->getJson('/api/services?clinic_slug=' . $this->clinicA->slug)->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->serviceA->id, $ids);
        $this->assertNotContains($this->serviceB->id, $ids);

        $this->getJson('/api/services?clinic_slug=slug-tidak-ada-' . strtolower($this->suffix))
            ->assertStatus(404);

        $this->getJson('/api/services/' . $this->serviceB->id . '?clinic_slug=' . $this->clinicA->slug)
            ->assertStatus(404);
    }

    public function test_patient_services_catalog_is_global(): void
    {
        // MULTI-CLINIC (2026-11-11): pasien = akun global. Katalog layanan
        // lintas klinik adalah DASAR perbandingan harga; `users.clinic_id`
        // lama tidak boleh menyempitkannya. Staf tetap terkurung (diuji
        // terpisah di bawah).
        $res = $this->apiAs($this->userA)->getJson('/api/services')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->serviceA->id, $ids);
        $this->assertContains($this->serviceB->id, $ids);
    }

    public function test_clinic_staff_services_are_still_filtered_by_clinic(): void
    {
        $staffB = User::create([
            'name' => 'staffB ' . $this->suffix,
            'email' => 'staffb-' . $this->suffix . '@audit.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicB->id,
        ]);

        $res = $this->apiAs($staffB)->getJson('/api/services')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->serviceB->id, $ids);
        $this->assertNotContains($this->serviceA->id, $ids);
    }

    // ---------- C4: mass assignment ----------

    public function test_medical_record_clinic_id_is_persisted(): void
    {
        $booking = Booking::create([
            'user_id' => $this->userA->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'service_id' => $this->serviceA->id,
            'booking_date' => $this->bookingDate(), 'booking_time' => '11:00',
            'status' => 'pending', 'payment_status' => 'pending',
            'clinic_id' => $this->clinicA->id,
        ]);

        $record = MedicalRecord::create([
            'booking_id' => $booking->id, 'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id, 'diagnosis' => 'Audit',
            'treatment' => 'Audit', 'clinic_id' => $booking->clinic_id,
        ]);

        $this->assertEquals($this->clinicA->id, $record->fresh()->clinic_id);
    }

    public function test_booking_stores_payment_method_name(): void
    {
        $res = $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->bookingDate(),
            'booking_time' => '12:00',
            'payment_method_id' => $this->payMethod->id,
        ])->assertCreated();

        $booking = Booking::find($res->json('data.id'));
        $this->assertNotNull($booking);
        $this->assertEquals($this->payMethod->name, $booking->payment_method);
        $this->assertEquals($this->clinicA->id, $booking->clinic_id);
    }

    // ---------- C5: import isolation ----------

    public function test_import_is_scoped_to_own_clinic(): void
    {
        $import = new ServicesImport('update', $this->clinicA->id);
        $import->collection(collect([
            [
                // Same name as Clinic B's service: must NOT touch B's row.
                'name' => $this->serviceB->name,
                'description' => 'Hijack attempt',
                'price' => 999,
                'duration' => 30,
                'status' => 'active',
            ],
        ]));

        $this->assertEquals('Vaksinasi ' . $this->suffix, $this->serviceB->fresh()->name);
        $this->assertEquals(200000, (float) $this->serviceB->fresh()->price);

        $created = Service::where('clinic_id', $this->clinicA->id)
            ->where('name', $this->serviceB->name)
            ->first();
        $this->assertNotNull($created);
    }

    public function test_import_without_clinic_creates_nothing(): void
    {
        $before = Service::count();
        $import = new ServicesImport('update', null);
        $import->collection(collect([
            ['name' => 'Orphan ' . $this->suffix, 'price' => 10, 'status' => 'active'],
        ]));

        $this->assertEquals($before, Service::count());
        $this->assertNotEmpty($import->errorRows);
    }

    // ---------- helpers: fail-closed scope ----------

    public function test_apply_clinic_scope_fails_closed(): void
    {
        $this->actingAs($this->userA);
        $q = applyClinicScope(Service::query(), null, $this->nullUser);
        $this->assertEquals(0, $q->count());

        $this->actingAs($this->superAdmin);
        $this->assertGreaterThanOrEqual(2, applyClinicScope(Service::query(), null)->count());
    }
}
