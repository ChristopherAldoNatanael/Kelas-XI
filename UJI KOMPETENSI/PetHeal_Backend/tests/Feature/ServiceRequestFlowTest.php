<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengajuan layanan → evaluasi → penawaran → booking.
 *
 * Yang dikunci: tidak ada booking sebelum persetujuan, harga/jadwal berasal
 * dari penawaran klinik, penawaran kedaluwarsa tidak bisa dibayar dengan harga
 * lama, dan permintaan milik pengguna/klinik lain tidak bisa disentuh.
 */
class ServiceRequestFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private Clinic $otherClinic;
    private User $patient;
    private User $otherPatient;
    private Doctor $doctor;
    private Pet $pet;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = Str::lower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Layanan ' . $suffix, 'slug' => 'layanan-' . $suffix,
            'address' => 'Jl. Layanan', 'is_active' => true,
        ]);
        $this->otherClinic = Clinic::create([
            'name' => 'Lain ' . $suffix, 'slug' => 'lain-' . $suffix,
            'address' => 'Jl. Lain', 'is_active' => true,
        ]);

        $this->patient = User::create([
            'name' => 'Pemilik', 'email' => 'owner' . $suffix . '@layanan.test',
            'password' => Hash::make('password123'), 'role' => 'user',
        ]);
        $this->otherPatient = User::create([
            'name' => 'Pemilik Lain', 'email' => 'other' . $suffix . '@layanan.test',
            'password' => Hash::make('password123'), 'role' => 'user',
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Layanan ' . $suffix, 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinic->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->patient->id, 'name' => 'LayananPet', 'species' => 'Dog', 'gender' => 'female',
        ]);

        $this->service = Service::create([
            'clinic_id' => $this->clinic->id, 'name' => 'USG Abdomen',
            'price' => 250000, 'is_active' => true,
        ]);
    }

    private function service(): ServiceRequestService
    {
        return app(ServiceRequestService::class);
    }

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('sr-test')->plainTextToken];
    }

    private function submittedRequest(array $overrides = []): ServiceRequest
    {
        return $this->service()->submit($this->patient, $this->pet, array_merge([
            'clinic_id' => $this->clinic->id,
            'need_type' => 'consultation',
            'complaint' => 'Hewan muntah berulang sejak dua hari lalu dan lemas.',
        ], $overrides));
    }

    private function offeredRequest(array $overrides = []): ServiceRequest
    {
        $request = $this->submittedRequest();
        $this->service()->advance($request, ServiceRequest::STATUS_UNDER_REVIEW);

        return $this->service()->offer($request->fresh(), array_merge([
            'offer_service_id' => $this->service->id,
            'offer_service_name' => $this->service->name,
            'offer_price' => 250000,
            'offer_price_type' => 'final',
            'offer_doctor_id' => $this->doctor->id,
            'offer_schedule_date' => today()->addDay()->toDateString(),
            'offer_schedule_time' => '10:00:00',
            'offer_valid_until' => now()->addDays(3),
        ], $overrides));
    }

    // ---------- Pengajuan ----------

    public function test_submit_creates_request_without_booking_or_payment(): void
    {
        $request = $this->submittedRequest();

        $this->assertSame(ServiceRequest::STATUS_SUBMITTED, $request->status);
        $this->assertStringStartsWith('SR-', $request->code);
        $this->assertNull($request->booking_id);
        $this->assertSame(0, Booking::count());
    }

    public function test_follow_up_request_records_medical_record_link(): void
    {
        $booking = Booking::create([
            'user_id' => $this->patient->id, 'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id, 'service_id' => $this->service->id,
            'clinic_id' => $this->clinic->id, 'booking_date' => today()->toDateString(),
            'booking_time' => '09:00:00', 'status' => 'completed', 'payment_status' => 'paid',
            'total_amount' => 250000, 'paid_amount' => 250000, 'remaining_amount' => 0,
        ]);
        $record = MedicalRecord::create([
            'booking_id' => $booking->id, 'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id, 'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Gastritis', 'treatment' => 'Obat oral',
            'next_visit_date' => today()->addWeek()->toDateString(),
        ]);

        $request = $this->submittedRequest([
            'need_type' => 'follow_up',
            'medical_record_id' => $record->id,
        ]);

        $this->assertSame($record->id, $request->medical_record_id);
        $this->assertTrue($request->needs_follow_up);
    }

    // ---------- Transisi ----------

    public function test_illegal_transition_is_rejected(): void
    {
        $request = $this->submittedRequest();

        $this->expectException(RuntimeException::class);
        $this->service()->advance($request, ServiceRequest::STATUS_CONVERTED);
    }

    public function test_offer_sets_offered_status_and_can_be_updated(): void
    {
        $request = $this->offeredRequest();
        $this->assertSame(ServiceRequest::STATUS_OFFERED, $request->status);
        $this->assertSame('final', $request->offer_price_type);

        $updated = $this->service()->offer($request->fresh(), [
            'offer_service_name' => 'USG + Rontgen',
            'offer_price' => 400000,
            'offer_price_type' => 'estimate',
            'offer_doctor_id' => $this->doctor->id,
            'offer_schedule_date' => today()->addDay()->toDateString(),
            'offer_schedule_time' => '11:00:00',
        ]);

        $this->assertSame('estimate', $updated->status === ServiceRequest::STATUS_OFFERED ? $updated->offer_price_type : null);
        $this->assertSame('USG + Rontgen', $updated->offer_service_name);
    }

    // ---------- Persetujuan → booking ----------

    public function test_accept_creates_linked_booking_and_is_idempotent(): void
    {
        $bookingSource = Booking::create([
            'user_id' => $this->patient->id, 'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id, 'service_id' => $this->service->id,
            'clinic_id' => $this->clinic->id, 'booking_date' => today()->toDateString(),
            'booking_time' => '08:00:00', 'status' => 'completed', 'payment_status' => 'paid',
            'total_amount' => 250000, 'paid_amount' => 250000, 'remaining_amount' => 0,
        ]);
        $record = MedicalRecord::create([
            'booking_id' => $bookingSource->id, 'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id, 'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Kontrol pasca operasi', 'treatment' => 'Observasi',
        ]);

        $request = $this->submittedRequest([
            'need_type' => 'follow_up',
            'medical_record_id' => $record->id,
        ]);
        $this->service()->advance($request, ServiceRequest::STATUS_UNDER_REVIEW);
        $request = $this->service()->offer($request->fresh(), [
            'offer_service_name' => 'Kontrol pasca operasi',
            'offer_price' => 250000,
            'offer_price_type' => 'final',
            'offer_doctor_id' => $this->doctor->id,
            'offer_schedule_date' => today()->addDay()->toDateString(),
            'offer_schedule_time' => '10:00:00',
            'offer_valid_until' => now()->addDays(3),
        ]);

        $booking = $this->service()->accept($request->fresh());

        $this->assertSame('pending', $booking->status);
        $this->assertSame('full', $booking->payment_type);
        $this->assertSame(250000.0, (float) $booking->total_amount);
        $this->assertSame($record->id, $booking->medical_record_id);
        $this->assertSame($this->clinic->id, $booking->clinic_id);
        $this->assertSame($this->doctor->id, $booking->doctor_id);

        $converted = $request->fresh();
        $this->assertSame(ServiceRequest::STATUS_CONVERTED, $converted->status);
        $this->assertSame($booking->id, $converted->booking_id);
        $this->assertNotNull($converted->accepted_at);

        // Klik ganda tidak membuat booking kedua.
        $again = $this->service()->accept($converted);
        $this->assertSame($booking->id, $again->id);
        $this->assertSame(1, Booking::where('id', '!=', $bookingSource->id)->count());
    }

    public function test_expired_offer_cannot_be_accepted_and_creates_no_booking(): void
    {
        $request = $this->offeredRequest([
            'offer_valid_until' => now()->subHour(),
        ]);

        try {
            $this->service()->accept($request->fresh());
            $this->fail('Penawaran kedaluwarsa seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('kedaluwarsa', $e->getMessage());
        }

        $this->assertSame(ServiceRequest::STATUS_EXPIRED, $request->fresh()->status);
        $this->assertSame(0, Booking::count());
    }

    public function test_accept_requires_doctor_and_schedule_in_offer(): void
    {
        $request = $this->offeredRequest([
            'offer_doctor_id' => null,
            'offer_schedule_date' => null,
            'offer_schedule_time' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->accept($request->fresh());
    }

    public function test_reject_closes_request_without_booking(): void
    {
        $request = $this->offeredRequest();
        $rejected = $this->service()->reject($request->fresh(), 'Terlalu mahal');

        $this->assertSame(ServiceRequest::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Terlalu mahal', $rejected->owner_response_note);
        $this->assertNull($rejected->booking_id);
        $this->assertSame(0, Booking::count());
    }

    // ---------- API mobile ----------

    public function test_api_rejects_pet_owned_by_someone_else(): void
    {
        $this->withHeaders($this->headers($this->patient))
            ->postJson('/api/service-requests', [
                'clinic_id' => $this->clinic->id,
                'pet_id' => Pet::create([
                    'user_id' => $this->otherPatient->id, 'name' => 'Bukan Milik',
                    'species' => 'Cat', 'gender' => 'male',
                ])->id,
                'need_type' => 'consultation',
                'complaint' => 'Pemeriksaan umum untuk hewan ini.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pet_id');
    }

    public function test_api_creates_request_with_server_side_status(): void
    {
        $response = $this->withHeaders($this->headers($this->patient))
            ->postJson('/api/service-requests', [
                'clinic_id' => $this->clinic->id,
                'pet_id' => $this->pet->id,
                'need_type' => 'other',
                'complaint' => 'Butuh fisioterapi mingguan untuk kaki belakang.',
                'status' => ServiceRequest::STATUS_CONVERTED,
                'booking_id' => 999,
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $created = ServiceRequest::findOrFail($response->json('data.id'));

        $this->assertSame(ServiceRequest::STATUS_SUBMITTED, $created->status);
        $this->assertNull($created->booking_id);
        $this->assertSame($this->patient->id, $created->user_id);
    }

    public function test_api_hides_other_patients_request(): void
    {
        $request = $this->submittedRequest();

        $this->withHeaders($this->headers($this->otherPatient))
            ->getJson('/api/service-requests/' . $request->id)
            ->assertStatus(404);
    }

    public function test_api_accept_returns_created_booking(): void
    {
        $request = $this->offeredRequest();

        $response = $this->withHeaders($this->headers($this->patient))
            ->postJson('/api/service-requests/' . $request->id . '/accept', []);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ServiceRequest::STATUS_CONVERTED);

        $this->assertSame(
            $response->json('booking.id'),
            $request->fresh()->booking_id
        );
    }

    public function test_api_reject_requires_own_offered_request(): void
    {
        $request = $this->offeredRequest();

        $this->withHeaders($this->headers($this->otherPatient))
            ->postJson('/api/service-requests/' . $request->id . '/reject', ['note' => 'x'])
            ->assertStatus(404);

        $this->assertSame(ServiceRequest::STATUS_OFFERED, $request->fresh()->status);
    }

    // ---------- Web admin & dokter ----------

    private function clinicAdmin(): User
    {
        return User::create([
            'name' => 'Admin Layanan',
            'email' => 'admin-' . Str::random(8) . '@layanan.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);
    }

    public function test_admin_index_and_detail_render(): void
    {
        $request = $this->submittedRequest();
        $admin = $this->clinicAdmin();

        $this->actingAs($admin)->get('/admin/service-requests')
            ->assertOk()
            ->assertSee($request->code);

        $this->actingAs($admin)->get('/admin/service-requests/' . $request->id)
            ->assertOk()
            ->assertSee('Penawaran');

        // Super admin tanpa konteks klinik memilih klinik lebih dulu.
        $super = User::create([
            'name' => 'Super Layanan',
            'email' => 'super-' . Str::random(8) . '@layanan.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);
        $this->actingAs($super)->get('/admin/service-requests')
            ->assertOk()
            ->assertSee($this->clinic->name);
    }

    public function test_admin_can_send_offer_with_estimate_price(): void
    {
        $request = $this->submittedRequest();

        $this->actingAs($this->clinicAdmin())
            ->post('/admin/service-requests/' . $request->id, [
                'action' => 'offer',
                'offer_service_name' => 'USG + Rontgen',
                'offer_price' => 400000,
                'offer_price_type' => 'estimate',
                'offer_doctor_id' => $this->doctor->id,
                'offer_schedule_date' => today()->addDay()->toDateString(),
                'offer_schedule_time' => '10:30',
                'offer_valid_until' => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertRedirect('/admin/service-requests/' . $request->id);

        $offered = $request->fresh();
        $this->assertSame(ServiceRequest::STATUS_OFFERED, $offered->status);
        $this->assertSame('estimate', $offered->offer_price_type);
        $this->assertSame('10:30', $offered->offer_schedule_time);
        $this->assertNotNull($offered->offered_at);
    }

    public function test_admin_cannot_offer_other_clinic_request(): void
    {
        $request = $this->submittedRequest();
        $otherAdmin = User::create([
            'name' => 'Admin Lain',
            'email' => 'admin-' . Str::random(8) . '@layanan.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->otherClinic->id,
        ]);

        $this->actingAs($otherAdmin)
            ->get('/admin/service-requests/' . $request->id)
            ->assertNotFound();
    }

    public function test_doctor_recommendation_moves_to_waiting_offer(): void
    {
        $doctorUser = User::create([
            'name' => 'dr evaluasi ' . Str::random(4),
            'email' => 'dreval-' . Str::random(8) . '@layanan.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);
        $this->doctor->update(['user_id' => $doctorUser->id]);

        $request = $this->submittedRequest(['doctor_id' => $this->doctor->id]);
        $this->service()->advance($request, ServiceRequest::STATUS_UNDER_REVIEW);
        $this->service()->advance($request->fresh(), ServiceRequest::STATUS_WAITING_DOCTOR);

        $this->actingAs($doctorUser)->get('/doctor/service-requests')
            ->assertOk()
            ->assertSee($request->code);

        $this->actingAs($doctorUser)->get('/doctor/service-requests/' . $request->id)
            ->assertOk()
            ->assertSee('Rekomendasi Medis');

        $this->actingAs($doctorUser)
            ->post('/doctor/service-requests/' . $request->id . '/recommend', [
                'doctor_recommendation' => 'Perlu USG abdomen untuk menilai obstruksi.',
                'doctor_notes' => 'Puasa 8 jam sebelum pemeriksaan.',
            ])
            ->assertRedirect();

        $updated = $request->fresh();
        $this->assertSame(ServiceRequest::STATUS_WAITING_OFFER, $updated->status);
        $this->assertStringContainsString('USG abdomen', $updated->doctor_recommendation);
        // Dokter tidak menentukan harga.
        $this->assertNull($updated->offer_price);
    }

    public function test_admin_assigning_doctor_from_submitted_passes_through_review(): void
    {
        Http::fake(['*' => Http::response(['name' => 'projects/demo/messages/1'], 200)]);
        $request = $this->submittedRequest();

        $this->actingAs($this->clinicAdmin())
            ->post('/admin/service-requests/' . $request->id, [
                'action' => 'assign_doctor',
                'doctor_id' => $this->doctor->id,
            ])
            ->assertRedirect('/admin/service-requests/' . $request->id);

        $updated = $request->fresh();
        $this->assertSame(ServiceRequest::STATUS_WAITING_DOCTOR, $updated->status);
        $this->assertSame($this->doctor->id, (int) $updated->doctor_id);
    }

    public function test_admin_request_info_keeps_status_and_saves_note(): void
    {
        Http::fake(['*' => Http::response(['name' => 'projects/demo/messages/1'], 200)]);
        $request = $this->submittedRequest();
        $this->service()->advance($request, ServiceRequest::STATUS_UNDER_REVIEW);

        $this->actingAs($this->clinicAdmin())
            ->post('/admin/service-requests/' . $request->id, [
                'action' => 'request_info',
                'admin_notes' => 'Mohon kirim foto kondisi hewan.',
            ])
            ->assertRedirect('/admin/service-requests/' . $request->id);

        $updated = $request->fresh();
        $this->assertSame(ServiceRequest::STATUS_UNDER_REVIEW, $updated->status);
        $this->assertSame('Mohon kirim foto kondisi hewan.', $updated->admin_notes);
    }

    public function test_admin_offer_requires_explicit_price(): void
    {
        $request = $this->submittedRequest();
        $date = today()->addDay()->toDateString();

        $response = $this->actingAs($this->clinicAdmin())
            ->post('/admin/service-requests/' . $request->id, [
                'action' => 'offer',
                'offer_service_name' => 'USG Abdomen',
                'offer_doctor_id' => $this->doctor->id,
                'offer_schedule_date' => $date,
                'offer_schedule_time' => '10:00',
            ]);

        $response->assertSessionHas('error', 'Harga penawaran wajib diisi oleh admin.');
        $this->assertSame(ServiceRequest::STATUS_SUBMITTED, $request->fresh()->status);
        $this->assertNull($request->fresh()->offer_price);
    }

    public function test_admin_detail_exposes_doctor_schedule_metadata_for_offer_form(): void
    {
        $request = $this->submittedRequest();

        $this->actingAs($this->clinicAdmin())
            ->get('/admin/service-requests/' . $request->id)
            ->assertOk()
            ->assertSee('offerScheduleData', false)
            ->assertSee('Hari praktik', false)
            ->assertSee($this->doctor->name);
    }
}
