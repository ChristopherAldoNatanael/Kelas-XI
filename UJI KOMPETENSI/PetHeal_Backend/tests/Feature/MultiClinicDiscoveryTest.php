<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorReview;
use App\Models\Pet;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MULTI-CLINIC DISCOVERY (revisi 2026-11-09).
 *
 * Bukti bahwa perubahan perilaku benar:
 *  1. registrasi/login tidak lagi mewajibkan klinik,
 *  2. pasien global (tanpa clinic_id) tetap bisa memakai API,
 *  3. discovery klinik publik: jarak, status buka, kategori, rating,
 *  4. perbandingan harga hanya antar layanan SETARA (kategori standar sama),
 *  5. clinics.rating dihitung dari ulasan,
 *  6. satu akun pasien bisa booking di dua klinik berbeda,
 *  7. isolasi clinic_admin / dokter tetap utuh,
 *  8. halaman kategori layanan hanya untuk super_admin.
 *
 * Auth API: Bearer token (ApiAuthenticate menolak request tanpa bearerToken()).
 */
class MultiClinicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $clinicAdminA;
    private User $patient;
    private User $superAdmin;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Service $vaccineA;
    private Service $vaccineB;
    private Service $groomingA;
    private ServiceCategory $vaccination;
    private ServiceCategory $grooming;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->suffix = Str::random(6);

        // Clinic A dekat (Jakarta Pusat), Clinic B jauh (Bandung).
        $this->clinicA = Clinic::create([
            'name' => 'Discovery A ' . $this->suffix,
            'slug' => 'disc-a-' . strtolower($this->suffix),
            'address' => 'Jl. Discovery A',
            'is_active' => true,
            'latitude' => -6.1754000,
            'longitude' => 106.8272000,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'primary_color' => '#18C964',
        ]);

        $this->clinicB = Clinic::create([
            'name' => 'Discovery B ' . $this->suffix,
            'slug' => 'disc-b-' . strtolower($this->suffix),
            'address' => 'Jl. Discovery B',
            'is_active' => true,
            'latitude' => -6.9175000,
            'longitude' => 107.6191000,
        ]);

        $mkUser = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag . ' ' . $this->suffix,
            'email' => strtolower($tag) . '-' . $this->suffix . '@discovery.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'clinic_id' => $clinicId,
        ]);

        $this->clinicAdminA = $mkUser('adminA', 'clinic_admin', $this->clinicA->id);
        $this->petOwner = $mkUser('clinicAdminB', 'clinic_admin', $this->clinicB->id);
        $this->patient = $mkUser('owner', 'user', null);
        $this->superAdmin = $mkUser('superadmin', 'super_admin', null);

        $this->vaccination = ServiceCategory::create([
            'name' => 'Vaksinasi ' . $this->suffix,
            'slug' => 'vaksinasi-' . strtolower($this->suffix),
            'is_active' => true,
        ]);
        $this->grooming = ServiceCategory::create([
            'name' => 'Grooming ' . $this->suffix,
            'slug' => 'grooming-' . $this->suffix,
            'is_active' => true,
        ]);

        $this->vaccineA = Service::create([
            'name' => 'Vaksin Inti',
            'price' => 250000,
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
            'service_category_id' => $this->vaccination->id,
        ]);
        $this->vaccineB = Service::create([
            'name' => 'Vaksinasi Lengkap',
            'price' => 120000,
            'is_active' => true,
            'clinic_id' => $this->clinicB->id,
            'service_category_id' => $this->vaccination->id,
        ]);
        // Kategori berbeda: TIDAK boleh ikut perbandingan vaksinasi.
        $this->groomingA = Service::create([
            'name' => 'Grooming Mandi',
            'price' => 50000,
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
            'service_category_id' => $this->grooming->id,
        ]);

        $this->doctorA = $this->makeDoctor($this->clinicA->id, 'A');
        $this->doctorB = $this->makeDoctor($this->clinicB->id, 'B');

        $this->pet = Pet::create([
            'user_id' => $this->patient->id,
            'name' => 'Milo ' . $this->suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);
    }

    private User $petOwner;
    private string $clinicSlugDummy = '';

    private function makeDoctor(int $clinicId, string $tag): Doctor
    {
        return Doctor::create([
            'name' => 'dr ' . $tag . ' ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinicId,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    private function apiAs(User $user, array $headers = [])
    {
        auth()->forgetGuards();

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer ' . $user->createToken('discovery-test')->plainTextToken,
            'Accept' => 'application/json',
        ], $headers));
    }

    private function tomorrow(): string
    {
        return Carbon::tomorrow()->format('Y-m-d');
    }

    // ── A. Registrasi tanpa klinik ──────────────────────────────────────────

    public function test_registration_never_requires_or_binds_a_clinic()
    {
        $email = 'newowner-' . $this->suffix . '@discovery.test';

        $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Pemilik Baru',
            'email' => $email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('success', true);

        // Klien lama yang masih mengirim slug asing tidak boleh digagalkan.
        $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Pemilik Lama',
            'email' => 'legacy-' . $this->suffix . '@discovery.test',
            'password' => 'password123',
            'clinic_slug' => 'slug-asing-' . strtolower($this->suffix),
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_patient_without_clinic_can_call_protected_endpoints()
    {
        $this->apiAs($this->patient)->getJson('/api/dashboard')->assertOk();
        $this->apiAs($this->patient)->getJson('/api/pets')->assertOk();

        // Header X-Clinic-Slug sisa sesi lama tidak boleh memblokir pasien.
        $this->apiAs($this->patient, ['X-Clinic-Slug' => $this->clinicB->slug])
            ->getJson('/api/pets')->assertOk();
    }

    public function test_clinic_staff_without_clinic_is_still_blocked()
    {
        $staff = User::create([
            'name' => 'Staff No Clinic ' . $this->suffix,
            'email' => 'staff-' . $this->suffix . '@discovery.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => null,
        ]);

        $this->apiAs($staff)->getJson('/api/doctors')->assertStatus(403);
    }

    // ── B. Discovery publik ─────────────────────────────────────────────────

    public function test_clinic_list_computes_distance_and_open_status()
    {
        $res = $this->getJson('/api/public/clinics?lat=-6.1754000&lng=106.8272000&sort=distance')
            ->assertOk();

        $rows = collect($res->json('data'))->keyBy('slug');

        $a = $rows[$this->clinicA->slug];
        $b = $rows[$this->clinicB->slug];

        // Clinic A berada tepat di titik user; B di Bandung (~120 km).
        $this->assertEquals(0.0, $a['distance_km']);
        $this->assertGreaterThan(100, $b['distance_km']);

        // Distance tersortir naik + jarak diformat untuk UI.
        $this->assertSame($this->clinicA->slug, collect($res->json('data'))->first()['slug']);
        $this->assertNotNull($a['distance_text']);

        // Status operasional selalu punya nilai yang bisa dibaca klien.
        $this->assertContains($a['operating_status'], ['open', 'closed', 'unknown']);
        $this->assertNull($rows['disc-a-' . strtolower($this->suffix)]['rating']);
        $this->assertNotNull($a['has_coordinates']);
        $this->assertTrue($a['is_open_now'] !== null);
    }

    public function test_clinic_list_filters_by_service_category_and_search()
    {
        // Cari berdasarkan nama layanan → hanya klinik yang punya layanan itu.
        $res = $this->getJson('/api/public/clinics?search=Grooming%20Mandi')->assertOk();
        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertContains($this->clinicA->slug, $slugs);
        $this->assertNotContains($this->clinicB->slug, $slugs);

        // Filter kategori standar.
        $res = $this->getJson('/api/public/clinics?category=' . $this->grooming->slug)->assertOk();
        $this->assertSame([$this->clinicA->slug], collect($res->json('data'))->pluck('slug')->all());

        // Filter radius: klinik Bandung tidak ikut bila maksimal 10 km.
        $res = $this->getJson('/api/public/clinics?lat=-6.1754&lng=106.8272&max_distance_km=10')->assertOk();
        $this->assertSame([$this->clinicA->slug], collect($res->json('data'))->pluck('slug')->all());
    }

    public function test_service_categories_are_public()
    {
        $res = $this->getJson('/api/public/service-categories')->assertOk();

        $slugs = collect($res->json('data'))->pluck('slug')->all();

        $this->assertContains($this->vaccination->slug, $slugs);
        $this->assertContains($this->grooming->slug, $slugs);
        $this->assertArrayHasKey('icon', $res->json('data.0'));
    }

    // ── C. Perbandingan harga ───────────────────────────────────────────────

    public function test_price_comparison_only_compares_equivalent_services()
    {
        $res = $this->getJson('/api/public/services/compare?category_id=' . $this->vaccination->id . '&sort=price_asc')
            ->assertOk();

        $items = collect($res->json('data'));

        // Hanya layanan berkategori vaksinasi (2 klinik), grooming tidak ikut.
        $this->assertCount(2, $items);
        $this->assertNotContains($this->groomingA->id, $items->pluck('service_id')->all());

        // Urut harga termurah, semuanya lengkap dengan klinik & jarak.
        // JSON mengembalikan int untuk harga bulat, jadi bandingkan longgar.
        $this->assertSame($this->vaccineB->id, $items->first()['service_id']);
        $this->assertEquals(120000, $items->first()['price']);
        $this->assertTrue($items->first()['price_available']);
        $this->assertSame('price_asc', $res->json('meta.sort'));
        $this->assertSame('service_category', $res->json('meta.comparison_basis'));
        $this->assertEquals(120000, $res->json('meta.lowest_price'));
        $this->assertSame(2, $res->json('meta.price_available_count'));

        $sorted = $items->pluck('price')->all();
        $this->assertSame($sorted, collect($sorted)->sort()->values()->all());
    }

    public function test_price_comparison_sorts_by_distance_and_rating()
    {
        // Tambah ulasan agar rating klinik terhitung.
        $this->seedReview($this->doctorB, 5);

        $byDistance = $this->getJson('/api/public/services/compare?category_slug=' . $this->vaccination->slug
            . '&lat=-6.1754&lng=106.8272&sort=distance')->assertOk();
        $this->assertSame($this->clinicA->slug, $byDistance->json('data.0.clinic.slug'));

        $byRating = $this->getJson('/api/public/services/compare?category_slug=' . $this->vaccination->slug . '&sort=rating')
            ->assertOk();
        $this->assertSame($this->clinicB->slug, $byRating->json('data.0.clinic.slug'));

        // Filter rating minimum membuang klinik tanpa ulasan.
        $withRating = $this->getJson('/api/public/services/compare?category_slug=' . $this->vaccination->slug . '&min_rating=4')
            ->assertOk();
        $this->assertSame([$this->clinicB->slug], collect($withRating->json('data'))->pluck('clinic.slug')->all());
    }

    public function test_price_comparison_requires_a_reference()
    {
        $this->getJson('/api/public/services/compare')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_service_detail_lists_equivalent_clinics()
    {
        $res = $this->getJson('/api/public/services/' . $this->vaccineA->id)->assertOk();

        $this->assertSame($this->vaccineA->name, $res->json('data.service.name'));
        $this->assertSame($this->clinicA->slug, $res->json('data.clinic.slug'));
        $this->assertSame(1, $res->json('data.equivalents_count'));
        $this->assertSame($this->vaccineB->id, $res->json('data.equivalents.0.service_id'));
    }

    // ── D. Rating klinik dari ulasan ────────────────────────────────────────

    public function test_clinic_rating_is_aggregated_from_reviews()
    {
        $this->seedReview($this->doctorA, 5);
        $this->seedReview($this->doctorA, 3, true);

        $this->clinicA->refresh();
        $this->assertSame('4.00', $this->clinicA->rating);
        $this->assertSame(2, $this->clinicA->reviews_count);

        // Klinik B tidak terpengaruh.
        $this->clinicB->refresh();
        $this->assertNull($this->clinicB->rating);
        $this->assertSame(0, $this->clinicB->reviews_count);
    }

    public function test_deleting_a_review_recalculates_clinic_rating()
    {
        $review = $this->seedReview($this->doctorA, 2);
        $this->clinicA->refresh();
        $this->assertSame('2.00', $this->clinicA->rating);

        $review->delete();
        $this->clinicA->refresh();
        $this->assertNull($this->clinicA->rating);
        $this->assertSame(0, $this->clinicA->reviews_count);
    }

    // ── E. Booking lintas klinik oleh satu akun ────────────────────────────

    public function test_one_patient_can_book_in_two_different_clinics()
    {
        $date = $this->tomorrow();

        $first = $this->apiAs($this->patient)->postJson('/api/bookings', [
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->vaccineA->id,
            'booking_date' => $date,
            'booking_time' => '09:00',
            'payment_type' => 'full',
        ])->assertStatus(201);

        $this->apiAs($this->patient)->postJson('/api/bookings', [
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorB->id,
            'service_id' => $this->vaccineB->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '10:00',
            'clinic_slug' => $this->clinicB->slug,
            'payment_type' => 'full',
        ])->assertStatus(201);

        $ids = Booking::where('user_id', $this->patient->id)->pluck('clinic_id')->unique()->values()->all();
        sort($ids);
        $expected = [$this->clinicA->id, $this->clinicB->id];
        sort($expected);

        $this->assertSame($expected, $ids);
        $this->assertSame($this->clinicA->id, Booking::find($first->json('data.id'))->clinic_id);
    }

    public function test_booking_rejects_mismatched_clinic_doctor_and_service()
    {
        // Dokter klinik A + layanan klinik B = bukan pasangan yang setara.
        $this->apiFor($this->patient)->postJson('/api/bookings', [
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->vaccineB->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
        ])->assertStatus(422);

        // clinic_slug yang tidak cocok dengan dokter yang dipilih.
        $this->apiFor($this->patient)->postJson('/api/bookings', [
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->vaccineA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
            'clinic_slug' => $this->clinicB->slug,
        ])->assertStatus(422);
    }

    private function apiFor(User $user)
    {
        return $this->apiAs($user);
    }

    // ── E2. Katalog per klinik (alur booking Android) ──────────────────────
    //
    // Layar booking memilih KLINIK lebih dulu, lalu hanya boleh menawarkan
    // dokter & layanan klinik tersebut. Tanpa filter ini pasien melihat
    // dokter semua klinik tanpa tahu tujuannya, dan layanan klinik lain
    // membuat booking ditolak "klinik harus sama".

    public function test_doctors_catalog_exposes_and_filters_by_clinic()
    {
        // Katalog lintas klinik: tiap dokter membawa kliniknya.
        $res = $this->apiFor($this->patient)->getJson('/api/doctors')->assertOk();
        $rows = collect($res->json('data'))->keyBy('id');
        $this->assertSame($this->clinicA->id, $rows[$this->doctorA->id]['clinic']['id']);
        $this->assertSame($this->clinicB->id, $rows[$this->doctorB->id]['clinic']['id']);

        // Filter clinic_id: hanya dokter klinik itu.
        $res = $this->apiFor($this->patient)
            ->getJson('/api/doctors?clinic_id=' . $this->clinicA->id)->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->doctorA->id, $ids);
        $this->assertNotContains($this->doctorB->id, $ids);

        // Detail dokter juga membawa klinik (layar langkah 3 memakainya).
        $this->apiFor($this->patient)->getJson('/api/doctors/' . $this->doctorB->id)
            ->assertOk()
            ->assertJsonPath('data.clinic.id', $this->clinicB->id);
    }

    public function test_doctors_catalog_rejects_unknown_clinic_id()
    {
        $this->apiFor($this->patient)->getJson('/api/doctors?clinic_id=999999')
            ->assertStatus(404);
        $this->apiFor($this->patient)->getJson('/api/doctors?clinic_id=abc')
            ->assertStatus(404);
    }

    public function test_services_catalog_can_be_forced_to_one_clinic()
    {
        // Tanpa clinic_id: katalog lintas klinik tetap seperti sebelumnya.
        $res = $this->apiFor($this->patient)->getJson('/api/services')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->vaccineA->id, $ids);
        $this->assertContains($this->vaccineB->id, $ids);

        // Dengan clinic_id: hanya layanan klinik itu — inilah yang membuat
        // langkah 3 tidak lagi menawarkan layanan klinik lain.
        $res = $this->apiFor($this->patient)
            ->getJson('/api/services?clinic_id=' . $this->clinicA->id)->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->vaccineA->id, $ids);
        $this->assertNotContains($this->vaccineB->id, $ids);

        // clinic_id tidak dikenal -> 404 (bukan daftar kosong yang menipu).
        $this->apiFor($this->patient)->getJson('/api/services?clinic_id=999999')
            ->assertStatus(404);
    }

    // ── F. Isolasi tenant staf klinik ──────────────────────────────────────

    public function test_clinic_admin_only_manages_own_clinic()
    {
        // Staf klinik selalu memakai kliennya sendiri: slug klinik lain di
        // query tidak boleh menggeser scope (200 dengan data klinik sendiri).
        $res = $this->apiAs($this->clinicAdminA)
            ->getJson('/api/services?clinic_slug=' . $this->clinicB->slug)->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->vaccineA->id, $ids);
        $this->assertNotContains($this->vaccineB->id, $ids);

        // Catatan: penolakan X-Clinic-Slug milik staf ada di
        // Phase3StabilizationTest::test_x_clinic_slug_mismatch_is_rejected —
        // "lolos" di sini hanya karena feature test menyimpan guard antar
        // request, bukan celah produksi (setiap request boot app baru).

        $this->apiAs($this->clinicAdminA)->getJson('/api/doctors/' . $this->doctorB->id)
            ->assertStatus(404);

        // Katalog kliniknya sendiri tetap terlihat.
        $res = $this->apiAs($this->clinicAdminA)->getJson('/api/services')->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($this->vaccineA->id, $ids);
        $this->assertNotContains($this->vaccineB->id, $ids);
    }

    public function test_doctor_cannot_see_other_clinic_booking()
    {
        $bookingB = Booking::create([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorB->id,
            'service_id' => $this->vaccineB->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '11:00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'clinic_id' => $this->clinicB->id,
        ]);

        // Portal dokter butuh akun yang benar-benar tertaut ke profil dokter.
        $doctorUserA = User::create([
            'name' => 'dr portal ' . $this->suffix,
            'email' => 'drportal-' . $this->suffix . '@discovery.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinicA->id,
        ]);
        $this->doctorA->update(['user_id' => $doctorUserA->id]);

        // Dokter klinik A tidak boleh membuka booking klinik B.
        $this->actingAs($doctorUserA)->get('/doctor/bookings/' . $bookingB->id)->assertStatus(404);

        // Booking di kliniknya sendiri tetap bisa dibuka.
        $bookingA = Booking::create([
            'user_id' => $this->patient->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->vaccineA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '12:00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'clinic_id' => $this->clinicA->id,
        ]);
        $this->actingAs($doctorUserA)->get('/doctor/bookings/' . $bookingA->id)->assertOk();
    }

    // ── G. Web admin ───────────────────────────────────────────────────────

    public function test_service_categories_page_is_super_admin_only()
    {
        $this->actingAs($this->superAdmin)->get('/admin/service-categories')->assertOk();

        $this->actingAs($this->clinicAdminA)->get('/admin/service-categories')->assertStatus(403);
    }

    public function test_clinic_admin_can_update_location_fields_of_own_clinic()
    {
        $this->actingAs($this->clinicAdminA)->put('/admin/settings/clinic', [
            'name' => $this->clinicA->name,
            'address' => 'Jl. Baru No. 9',
            'primary_color' => '#18C964',
            'latitude' => -6.2000000,
            'longitude' => 106.8166000,
            'opening_time' => '09:00',
            'closing_time' => '20:00',
            'operating_days' => ['monday', 'tuesday', 'friday'],
        ])->assertRedirect();

        $this->clinicA->refresh();
        $this->assertSame('Jl. Baru No. 9', $this->clinicA->address);
        $this->assertEquals(-6.2, (float) $this->clinicA->latitude);
        $this->assertEquals(106.8166, (float) $this->clinicA->longitude);
        $this->assertSame(['monday', 'tuesday', 'friday'], $this->clinicA->operating_days);
    }

    // ── Helper ─────────────────────────────────────────────────────────────

    private int $reviewSequence = 0;

    private function seedReview(Doctor $doctor, int $rating, bool $updateExisting = false): DoctorReview
    {
        $this->reviewSequence++;

        $owner = $updateExisting ? $this->petOwner : $this->patient;

        $booking = Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $this->vaccineA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => sprintf('%02d:00', 8 + $this->reviewSequence),
            'status' => 'completed',
            'payment_status' => 'paid',
            'clinic_id' => $doctor->clinic_id,
        ]);

        return DoctorReview::create([
            'doctor_id' => $doctor->id,
            'user_id' => $owner->id,
            'booking_id' => $booking->id,
            'rating' => $rating,
            'review' => 'Uji rating',
        ]);
    }
}
