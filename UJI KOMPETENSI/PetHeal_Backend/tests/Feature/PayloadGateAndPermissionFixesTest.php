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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the payload/permission fixes:
 *
 * 1. Booking payloads carry the service (invoice + receipt show its name).
 * 2. The extra-payment gate is applied to every payload that serializes a
 *    medical record raw (dashboard, booking detail, pet detail).
 * 3. A clinic admin cannot edit another staff/doctor account.
 * 4. Medical-record writes invalidate the cached admin dashboard.
 * 5. A past follow-up date is rejected only when it is actually changed.
 * 6. User passwords require the same 8 characters as self-service.
 */
class PayloadGateAndPermissionFixesTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;
    private User $owner;
    private User $clinicAdmin;
    private User $staffAdmin;
    private Doctor $doctor;
    private Service $service;
    private Pet $pet;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'PG ' . $this->suffix,
            'slug' => 'pg-' . $this->suffix,
            'address' => 'Jl. PG',
            'is_active' => true,
        ]);

        $mkUser = function (string $tag, string $role = 'user') {
            return User::create([
                'name' => $tag,
                'email' => $tag . $this->suffix . '@pg.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $this->clinic->id,
            ]);
        };

        $this->owner = $mkUser('pgowner');
        $this->clinicAdmin = $mkUser('pgadmin', 'clinic_admin');
        $this->staffAdmin = $mkUser('pgstaff', 'clinic_admin');

        $this->doctor = Doctor::create([
            'name' => 'Dr PG',
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ]);

        $this->service = Service::create([
            'name' => 'Grooming Premium',
            'price' => 175000,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);

        $this->pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'PG Pet',
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->owner->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_type' => 'full',
            'total_amount' => 175000,
            'paid_amount' => 0,
            'remaining_amount' => 175000,
            'clinic_id' => $this->clinic->id,
        ]);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('payload-gate-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    private function lockedRecord(string $diagnosis = 'Diagnosis terkunci'): MedicalRecord
    {
        return MedicalRecord::create([
            'booking_id' => $this->booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => $diagnosis,
            'treatment' => 'Terapi terkunci',
            'medicine' => 'Obat terkunci',
            'notes' => 'Catatan terkunci',
            'cost' => 100000,
            'treatment_cost' => 50000,
            'medicine_cost' => 25000,
            'extra_payment_amount' => 50000,
            'extra_payment_paid_amount' => 0,
            'extra_payment_status' => 'unpaid',
        ]);
    }

    public function test_booking_payloads_include_the_service(): void
    {
        $list = $this->apiAs($this->owner)->getJson('/api/bookings');
        $list->assertOk();
        $this->assertSame($this->service->name, $list->json('data.0.service.name'));

        $detail = $this->apiAs($this->owner)->getJson('/api/bookings/' . $this->booking->id);
        $detail->assertOk();
        $this->assertSame($this->service->name, $detail->json('data.service.name'));
        $this->assertSame((float) $this->service->price, (float) $detail->json('data.service.price'));
    }

    public function test_dashboard_masks_clinical_fields_of_locked_records(): void
    {
        $record = $this->lockedRecord();

        $response = $this->apiAs($this->owner)->getJson('/api/dashboard');
        $response->assertOk();

        $visit = $response->json('data.medical.recent_visits.0');
        $this->assertSame($record->id, $visit['id']);
        $this->assertNull($visit['diagnosis']);
        $this->assertNull($visit['treatment']);
        $this->assertNull($visit['medicine']);
        $this->assertNull($visit['notes']);
        // Payment state stays visible so the UI can offer the unlock action.
        $this->assertSame('unpaid', $visit['extra_payment_status']);
    }

    public function test_dashboard_keeps_clinical_fields_for_paid_records(): void
    {
        MedicalRecord::create([
            'booking_id' => $this->booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Diagnosis terbuka',
            'treatment' => 'Terapi terbuka',
            'extra_payment_status' => 'not_required',
        ]);

        $response = $this->apiAs($this->owner)->getJson('/api/dashboard');
        $response->assertOk();

        $this->assertSame('Diagnosis terbuka', $response->json('data.medical.recent_visits.0.diagnosis'));
    }

    public function test_booking_and_pet_detail_mask_locked_records(): void
    {
        $this->lockedRecord();

        $booking = $this->apiAs($this->owner)->getJson('/api/bookings/' . $this->booking->id);
        $booking->assertOk();
        $this->assertNull($booking->json('data.medicalRecord.diagnosis'));

        $pet = $this->apiAs($this->owner)->getJson('/api/pets/' . $this->pet->id);
        $pet->assertOk();
        $this->assertNull($pet->json('data.medical_records.0.diagnosis'));
    }

    public function test_clinic_admin_cannot_edit_another_staff_account(): void
    {
        $originalEmail = $this->staffAdmin->email;

        $this->actingAs($this->clinicAdmin)
            ->get('/admin/users/' . $this->staffAdmin->id . '/edit')
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->actingAs($this->clinicAdmin)
            ->put('/admin/users/' . $this->staffAdmin->id, [
                'name' => 'Dibajak',
                'email' => 'hijacked' . $this->suffix . '@pg.test',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertSame($originalEmail, $this->staffAdmin->fresh()->email);
        $this->assertNotSame('Dibajak', $this->staffAdmin->fresh()->name);
    }

    public function test_clinic_admin_can_still_edit_patient_account(): void
    {
        $this->actingAs($this->clinicAdmin)
            ->put('/admin/users/' . $this->owner->id, [
                'name' => 'Nama Pasien Baru',
                'email' => $this->owner->email,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Pasien Baru', $this->owner->fresh()->name);
    }

    public function test_medical_record_writes_invalidate_dashboard_cache(): void
    {
        $dashboardKey = 'dashboard_stats_v3_c' . $this->clinic->id . '_all';
        $paymentKey = 'payment_stats_c' . $this->clinic->id;

        Cache::put($dashboardKey, ['revenue' => 1], 300);
        Cache::put($paymentKey, ['total' => 1], 300);

        $this->actingAs($this->clinicAdmin)
            ->post('/admin/medical-records', [
                'booking_id' => $this->booking->id,
                'diagnosis' => 'Flu ringan',
                'treatment' => 'Istirahat',
                'cost' => 100000,
            ])
            ->assertRedirect(route('admin.bookings.index'));

        $this->assertFalse(Cache::has($dashboardKey));
        $this->assertFalse(Cache::has($paymentKey));

        $record = MedicalRecord::where('booking_id', $this->booking->id)->firstOrFail();

        Cache::put($dashboardKey, ['revenue' => 2], 300);
        Cache::put($paymentKey, ['total' => 2], 300);

        $this->actingAs($this->clinicAdmin)
            ->delete('/admin/medical-records/' . $record->id)
            ->assertRedirect(route('admin.medical-records.index'));

        $this->assertFalse(Cache::has($dashboardKey));
        $this->assertFalse(Cache::has($paymentKey));
    }

    public function test_follow_up_date_must_be_future_only_when_changed(): void
    {
        $record = MedicalRecord::create([
            'booking_id' => $this->booking->id,
            'pet_id' => $this->pet->id,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'diagnosis' => 'Kontrol lama',
            'treatment' => 'Terapi',
            'next_visit_date' => '2020-01-05',
            'extra_payment_status' => 'not_required',
        ]);

        // Unchanged past date is allowed — the record stays editable.
        $this->actingAs($this->clinicAdmin)
            ->put('/admin/medical-records/' . $record->id, [
                'diagnosis' => 'Kontrol lama (revisi)',
                'treatment' => 'Terapi',
                'next_visit_date' => '2020-01-05',
            ])
            ->assertRedirect(route('admin.medical-records.index'))
            ->assertSessionHasNoErrors();

        // A newly chosen past date is rejected.
        $this->actingAs($this->clinicAdmin)
            ->put('/admin/medical-records/' . $record->id, [
                'diagnosis' => 'Kontrol lama (revisi)',
                'treatment' => 'Terapi',
                'next_visit_date' => '2020-02-02',
            ])
            ->assertSessionHasErrors('next_visit_date');
    }

    public function test_user_password_requires_eight_characters(): void
    {
        $this->actingAs($this->clinicAdmin)
            ->post('/admin/users', [
                'name' => 'Pasien Pendek',
                'email' => 'short' . $this->suffix . '@pg.test',
                'password' => '1234567',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->clinicAdmin)
            ->post('/admin/users', [
                'name' => 'Pasien Cukup',
                'email' => 'ok' . $this->suffix . '@pg.test',
                'password' => '12345678',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'ok' . $this->suffix . '@pg.test',
        ]);
    }
}
