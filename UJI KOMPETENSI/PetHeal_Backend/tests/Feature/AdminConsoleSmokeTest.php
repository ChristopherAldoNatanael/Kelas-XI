<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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
 * Smoke test: every admin console page must render 200 in both locales.
 *
 * The admin console was rewritten page by page (shared panel/badge/stat-card
 * components + bilingual copy). A single broken blade file only shows up as a
 * 500 on that one page, so this walks the whole console in ID and EN.
 *
 * The redirect asserted at the end is long-standing behaviour:
 * `payments/{id}` opens the booking detail (one booking = one record).
 * Clinic profile is no longer its own page; `/admin/clinic-profile` must
 * redirect to the clinic tab in Settings.
 */
class AdminConsoleSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_admin_pages_render(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke ' . $suffix,
            'slug' => 'smoke-' . $suffix,
            'address' => 'Jl. Smoke',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokeadmin' . $suffix,
            'email' => 'smokeadmin' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $super = User::create([
            'name' => 'smokesuper' . $suffix,
            'email' => 'smokesuper' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);

        $owner = User::create([
            'name' => 'Smoke Owner ' . $suffix,
            'email' => 'smokeowner' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'Smoke Pet ' . $suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Smoke ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $service = Service::create([
            'name' => 'Smoke Service ' . $suffix,
            'description' => 'x',
            'price' => 50000,
            'duration_minutes' => 30,
            'is_active' => true,
            'clinic_id' => $clinic->id,
        ]);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_type' => 'full',
            'total_amount' => 50000,
            'paid_amount' => 50000,
            'remaining_amount' => 0,
            'clinic_id' => $clinic->id,
        ]);

        $record = MedicalRecord::create([
            'booking_id' => $booking->id,
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'service_id' => $service->id,
            'clinic_id' => $clinic->id,
            'diagnosis' => 'Sehat',
            'treatment' => 'Observasi',
            'notes' => 'x',
        ]);

        $pages = [
            '/admin',
            '/admin/schedule',
            '/admin/bookings',
            '/admin/bookings/' . $booking->id,
            '/admin/doctors',
            '/admin/doctors/create',
            '/admin/doctors/' . $doctor->id,
            '/admin/doctors/' . $doctor->id . '/edit',
            '/admin/doctors/' . $doctor->id . '/schedule',
            '/admin/services',
            '/admin/services/create',
            '/admin/services/' . $service->id . '/edit',
            '/admin/medical-records',
            '/admin/medical-records/' . $record->id,
            '/admin/medical-records/' . $record->id . '/edit',
            '/admin/medical-records/create/' . $booking->id,
            '/admin/users',
            '/admin/users/create',
            '/admin/users/' . $owner->id,
            '/admin/users/' . $owner->id . '/edit',
            '/admin/payments',
            '/admin/audit-logs',
            '/admin/settings',
            '/admin/settings?tab=keamanan',
            '/admin/settings?tab=klinik',
        ];

        foreach ($pages as $page) {
            foreach (['id', 'en'] as $locale) {
                $response = $this->actingAs($admin)
                    ->withSession(['app_locale' => $locale])
                    ->get($page);
                $this->assertSame(200, $response->getStatusCode(), "FAILED PAGE [{$locale}]: {$page} -> " . $response->headers->get('Location'));
            }
        }

        $superPages = [
            '/admin',
            '/admin/clinics',
            '/admin/clinics/create',
            '/admin/clinics/' . $clinic->id . '/edit',
            '/admin/join-requests',
            '/admin/notification-settings',
            '/admin/schedule',
        ];

        // payments/{id} diarahkan ke detail booking (perilaku lama).
        $this->actingAs($admin)
            ->get('/admin/payments/' . $booking->id)
            ->assertRedirect(route('admin.bookings.show', $booking->id) . '#pembayaran');

        // Profil klinik sudah dikonsolidasikan ke tab Pengaturan.
        $this->actingAs($admin)
            ->get('/admin/clinic-profile')
            ->assertRedirect(route('admin.settings', ['tab' => 'klinik']));

        // Tab tim lama tidak lagi menjadi halaman terpisah; query lama harus
        // fallback ke tab akun, bukan 404 atau 500.
        $this->actingAs($admin)
            ->get('/admin/settings?tab=tim')
            ->assertOk()
            ->assertSee(__('admin.tab_account'), false);

        foreach ($superPages as $page) {
            foreach (['id', 'en'] as $locale) {
                $response = $this->actingAs($super)
                    ->withSession(['app_locale' => $locale])
                    ->get($page);
                $this->assertSame(200, $response->getStatusCode(), "FAILED PAGE [{$locale}]: {$page} -> " . $response->headers->get('Location'));
            }
        }
    }

    /**
     * Daftar yang panjang memaksa paginator benar-benar merender link halaman.
     *
     * View pagination kustom pernah memanggil `$paginator->elements()` — method
     * itu `protected`, jadi `__call` meneruskannya ke Koleksi dan melempar
     * BadMethodCallException (halaman 500). Karena error baru muncul saat
     * jumlah baris melewati satu halaman, test ini membuat 25 booking.
     */
    public function test_paginated_admin_lists_render_page_links(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke Page ' . $suffix,
            'slug' => 'smoke-page-' . $suffix,
            'address' => 'Jl. Smoke',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokepage' . $suffix,
            'email' => 'smokepage' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $owner = User::create([
            'name' => 'Smoke Page Owner ' . $suffix,
            'email' => 'smokepageowner' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'Smoke Page Pet ' . $suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Smoke Page ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        for ($i = 0; $i < 25; $i++) {
            Booking::create([
                'user_id' => $owner->id,
                'pet_id' => $pet->id,
                'doctor_id' => $doctor->id,
                'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
                'booking_time' => sprintf('%02d:00', 8 + ($i % 9)),
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_type' => 'full',
                'total_amount' => 50000,
                'paid_amount' => 50000,
                'remaining_amount' => 0,
                'clinic_id' => $clinic->id,
            ]);
        }

        foreach (['/admin/bookings', '/admin/payments'] as $page) {
            $response = $this->actingAs($admin)->get($page);
            $response->assertOk();
            // Link halaman harus benar-benar dirender (bukan hanya "1").
            $response->assertSee('page=2', false);
        }
    }

    /**
     * Status pembayaran `dp_pending` (disimpan saat booking DP dibuat dari app)
     * tidak punya key terjemah — teks literal "common.dp_pending" pernah tampil
     * di daftar pembayaran. Test ini mengunci label yang benar.
     */
    public function test_dp_pending_bookings_show_a_translated_label(): void
    {        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke DP ' . $suffix,
            'slug' => 'smoke-dp-' . $suffix,
            'address' => 'Jl. Smoke DP',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokedp' . $suffix,
            'email' => 'smokedp' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $owner = User::create([
            'name' => 'Smoke DP Owner ' . $suffix,
            'email' => 'smokedpowner' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'Smoke DP Pet ' . $suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Smoke DP ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'pending',
            'payment_type' => 'dp',
            'payment_status' => 'dp_pending',
            'total_amount' => 50000,
            'dp_amount' => 25000,
            'paid_amount' => 0,
            'remaining_amount' => 25000,
            'clinic_id' => $clinic->id,
        ]);

        foreach (['id', 'en'] as $locale) {
            foreach (['/admin/payments', '/admin/bookings', '/admin/bookings/' . $booking->id] as $page) {
                $response = $this->actingAs($admin)
                    ->withSession(['app_locale' => $locale])
                    ->get($page);

                $response->assertOk();
                $response->assertSee(__('common.dp_pending'), false);
                $response->assertDontSee('common.dp_pending', false);
                $response->assertDontSee('admin.filter', false);
            }
        }
    }

    /**
     * Deleting a patient is a destructive action; the admin console must
     * never silently cascade away invoices, medical records, or staff.
     */
    public function test_user_deletion_is_guarded(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke Delete ' . $suffix,
            'slug' => 'smoke-delete-' . $suffix,
            'address' => 'Jl. Smoke Delete',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokedelete' . $suffix,
            'email' => 'smokedelete' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $otherAdmin = User::create([
            'name' => 'smokedeleteother' . $suffix,
            'email' => 'smokedeleteother' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $patientWithHistory = User::create([
            'name' => 'Smoke History ' . $suffix,
            'email' => 'smokehistory' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $patientWithoutHistory = User::create([
            'name' => 'Smoke Empty ' . $suffix,
            'email' => 'smokeempty' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $patientWithHistory->id,
            'name' => 'Smoke Delete Pet ' . $suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Smoke Delete ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        Booking::create([
            'user_id' => $patientWithHistory->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_type' => 'full',
            'total_amount' => 50000,
            'paid_amount' => 50000,
            'remaining_amount' => 0,
            'clinic_id' => $clinic->id,
        ]);

        // Patient with booking history: blocked, row stays.
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $patientWithHistory->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('admin.cannot_delete_user_with_history'));
        $this->assertDatabaseHas('users', ['id' => $patientWithHistory->id]);

        // Self-deletion: blocked with a specific message.
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('admin.cannot_delete_self'));
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        // Other admin accounts: blocked, even when they have no bookings.
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $otherAdmin->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('admin.cannot_delete_admin'));
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);

        // Patient without history: allowed.
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $patientWithoutHistory->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', __('admin.user_deleted'));
        $this->assertDatabaseMissing('users', ['id' => $patientWithoutHistory->id]);
    }

    public function test_payment_confirmation_rejects_overpayment(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke Pay ' . $suffix,
            'slug' => 'smoke-pay-' . $suffix,
            'address' => 'Jl. Smoke Pay',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokepay' . $suffix,
            'email' => 'smokepay' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $owner = User::create([
            'name' => 'Smoke Pay Owner ' . $suffix,
            'email' => 'smokepayowner' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'Smoke Pay Pet ' . $suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Smoke Pay ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'confirmed',
            'payment_type' => 'dp',
            'payment_status' => 'dp_paid',
            'total_amount' => 100000,
            'dp_amount' => 25000,
            'paid_amount' => 25000,
            'remaining_amount' => 75000,
            'clinic_id' => $clinic->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.confirm', $booking->id), ['amount' => 100000])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'paid_amount' => 25000,
            'remaining_amount' => 75000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.confirm', $booking->id), ['amount' => 75000])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
            'payment_status' => 'paid',
        ]);
    }

    public function test_pdf_receipt_and_exports_render_in_both_locales(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke PDF ' . $suffix,
            'slug' => 'smoke-pdf-' . $suffix,
            'address' => 'Jl. Smoke PDF',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokepdf' . $suffix,
            'email' => 'smokepdf' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $owner = User::create([
            'name' => 'Smoke PDF Owner ' . $suffix,
            'email' => 'smokepdfowner' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $clinic->id,
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'Smoke PDF Pet ' . $suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Smoke PDF ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $booking = Booking::create([
            'user_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'confirmed',
            'payment_type' => 'dp',
            'payment_status' => 'dp_pending',
            'total_amount' => 100000,
            'dp_amount' => 25000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'clinic_id' => $clinic->id,
        ]);

        foreach (['id', 'en'] as $locale) {
            $this->actingAs($admin)
                ->withSession(['app_locale' => $locale])
                ->get(route('admin.payments.receipt', $booking->id))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->actingAs($admin)
                ->withSession(['app_locale' => $locale])
                ->get(route('admin.payments.export', ['status' => 'dp_pending']))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_audit_log_action_keys_are_human_readable(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Smoke Audit ' . $suffix,
            'slug' => 'smoke-audit-' . $suffix,
            'address' => 'Jl. Smoke Audit',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'smokeaudit' . $suffix,
            'email' => 'smokeaudit' . $suffix . '@smoke.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'clinic_id' => $clinic->id,
            'action' => 'admin.password.change',
            'description' => 'Password changed by smoke test',
            'ip_address' => '127.0.0.1',
        ]);

        foreach (['id', 'en'] as $locale) {
            $this->actingAs($admin)
                ->withSession(['app_locale' => $locale])
                ->get(route('admin.audit-logs'))
                ->assertOk()
                ->assertSee(__('admin.audit_action_admin_password_change', [], $locale), false)
                ->assertDontSee('admin.password.change', false);
        }
    }
}
