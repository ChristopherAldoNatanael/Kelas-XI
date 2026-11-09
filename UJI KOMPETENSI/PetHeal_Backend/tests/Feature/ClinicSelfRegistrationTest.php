<?php

namespace Tests\Feature;

use App\Mail\ClinicRegistrationApprovedMail;
use App\Mail\ClinicRegistrationRejectedMail;
use App\Mail\OtpMail;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pendaftaran klinik mandiri (online):
 * - email pemilik diverifikasi dulu, belum ada klinik/akun;
 * - guardrail slug/email/duplikat dicek sebelum kode dan saat approve;
 * - approve membuat tepat 1 clinic + 1 clinic_admin secara atomik;
 * - reject tidak membuat data apa pun;
 * - alur lama "gabung klinik" tetap sama.
 */
class ClinicSelfRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super.' . $this->suffix . '@petheal.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'registration_type' => ClinicJoinRequest::TYPE_NEW_CLINIC,
            'clinic_name' => 'Klinik Mandiri ' . $this->suffix,
            'clinic_slug' => 'klinik-mandiri-' . $this->suffix,
            'clinic_address' => 'Jl. Mandiri No. 1',
            'clinic_phone' => '021-555000',
            'clinic_email' => 'info.' . $this->suffix . '@klinik.test',
            'clinic_primary_color' => '#18C964',
            'clinic_description' => 'Klinik hewan mandiri',
            'name' => 'Pemilik Klinik',
            'email' => 'owner.' . $this->suffix . '@petheal.test',
            'phone' => '081200000001',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    private function sentCode(): string
    {
        return Mail::sent(OtpMail::class)->firstOrFail()->code;
    }

    private function mkPendingRequest(array $overrides = []): ClinicJoinRequest
    {
        return ClinicJoinRequest::create(array_merge([
            'type' => ClinicJoinRequest::TYPE_NEW_CLINIC,
            'clinic_id' => null,
            'name' => 'Pemilik Klinik',
            'email' => 'pending.' . $this->suffix . '@petheal.test',
            'phone' => '081200000002',
            'password_hash' => Hash::make('rahasia123'),
            'status' => 'pending',
            'requested_clinic_name' => 'Klinik Pending ' . $this->suffix,
            'requested_clinic_slug' => 'klinik-pending-' . $this->suffix,
            'requested_clinic_address' => 'Jl. Pending No. 2',
            'requested_clinic_phone' => '021-555111',
            'requested_primary_color' => '#14794B',
        ], $overrides));
    }

    // ---------- registrasi + OTP ----------

    public function test_registering_new_clinic_sends_code_without_creating_data(): void
    {
        Mail::fake();

        $payload = $this->payload();

        $this->post('/admin/register', $payload)->assertRedirect(route('admin.register'));

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($payload) {
            return $mail->purpose === 'admin_register'
                && $mail->clinicName === $payload['clinic_name'];
        });

        // Belum ada request, klinik, atau akun sebelum kode diverifikasi.
        $this->assertDatabaseMissing('clinic_join_requests', ['email' => $payload['email']]);
        $this->assertDatabaseMissing('clinics', ['slug' => $payload['clinic_slug']]);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_register_page_exposes_both_registration_modes(): void
    {
        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('Gabung klinik')
            ->assertSee('Daftarkan klinik baru')
            ->assertSee('Nama klinik')
            ->assertSee('Data pemilik');
    }

    public function test_verified_code_creates_pending_new_clinic_request_only(): void
    {
        Mail::fake();

        $payload = $this->payload();
        $this->post('/admin/register', $payload)->assertRedirect();

        $this->post('/admin/register/verifikasi', ['code' => str_split($this->sentCode())])
            ->assertRedirect(route('admin.register'));

        $request = ClinicJoinRequest::where('email', $payload['email'])->firstOrFail();
        $this->assertSame(ClinicJoinRequest::TYPE_NEW_CLINIC, $request->type);
        $this->assertNull($request->clinic_id);
        $this->assertSame('pending', $request->status);
        $this->assertSame($payload['clinic_name'], $request->requested_clinic_name);
        $this->assertSame($payload['clinic_slug'], $request->requested_clinic_slug);
        $this->assertSame($payload['clinic_address'], $request->requested_clinic_address);
        $this->assertSame($payload['clinic_primary_color'], $request->requested_primary_color);
        $this->assertTrue(Hash::check('rahasia123', $request->password_hash));

        $this->assertDatabaseMissing('clinics', ['slug' => $payload['clinic_slug']]);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);

        // Halaman yang sama menampilkan status (tanpa halaman status kedua).
        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('Permintaan sedang ditinjau')
            ->assertSee($payload['clinic_name'], false);
    }

    public function test_wrong_code_does_not_create_request(): void
    {
        Mail::fake();

        $payload = $this->payload();
        $this->post('/admin/register', $payload)->assertRedirect();

        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post('/admin/register/verifikasi', ['code' => str_split($wrong)])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('clinic_join_requests', ['email' => $payload['email']]);
    }

    // ---------- guardrail ----------

    public function test_slug_collision_with_existing_clinic_is_rejected_before_code(): void
    {
        Mail::fake();

        $existing = Clinic::create([
            'name' => 'Klinik Lama ' . $this->suffix,
            'slug' => 'klinik-lama-' . $this->suffix,
            'address' => 'Jl. Lama',
        ]);

        $this->post('/admin/register', $this->payload(['clinic_slug' => $existing->slug]))
            ->assertSessionHasErrors('clinic_slug');

        Mail::assertNothingSent();
    }

    public function test_slug_collision_with_pending_request_is_rejected(): void
    {
        Mail::fake();

        $pending = $this->mkPendingRequest();

        $this->post('/admin/register', $this->payload([
            'clinic_slug' => $pending->requested_clinic_slug,
        ]))->assertSessionHasErrors('clinic_slug');

        Mail::assertNothingSent();
    }

    public function test_duplicate_clinic_name_and_phone_is_rejected(): void
    {
        Mail::fake();

        Clinic::create([
            'name' => 'Klinik Sama ' . $this->suffix,
            'slug' => 'klinik-sama-' . $this->suffix,
            'address' => 'Jl. Sama',
            'phone' => '021-777888',
        ]);

        $this->post('/admin/register', $this->payload([
            'clinic_name' => 'Klinik Sama ' . $this->suffix,
            'clinic_slug' => 'slug-beda-' . $this->suffix,
            'clinic_phone' => '021-777888',
        ]))->assertSessionHasErrors('clinic_name');

        Mail::assertNothingSent();
    }

    public function test_registered_email_and_pending_email_are_rejected(): void
    {
        Mail::fake();

        User::create([
            'name' => 'Existing',
            'email' => 'existing.' . $this->suffix . '@petheal.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => null,
        ]);

        $this->post('/admin/register', $this->payload([
            'email' => 'existing.' . $this->suffix . '@petheal.test',
        ]))->assertSessionHasErrors('email');

        $pending = $this->mkPendingRequest([
            'email' => 'waiting.' . $this->suffix . '@petheal.test',
        ]);

        $this->post('/admin/register', $this->payload([
            'email' => $pending->email,
        ]))->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    // ---------- approve / reject ----------

    public function test_approve_creates_one_clinic_and_one_admin_atomically(): void
    {
        Mail::fake();

        $request = $this->mkPendingRequest();

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/approve')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $clinic = Clinic::where('slug', $request->requested_clinic_slug)->firstOrFail();
        $this->assertSame($request->requested_clinic_name, $clinic->name);
        $this->assertTrue((bool) $clinic->is_active);

        $admin = User::where('email', $request->email)->firstOrFail();
        $this->assertSame('clinic_admin', $admin->role);
        $this->assertSame($clinic->id, $admin->clinic_id);
        $this->assertTrue(Hash::check('rahasia123', $admin->password));

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($clinic->id, $request->fresh()->clinic_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.registration.approved']);

        Mail::assertSent(ClinicRegistrationApprovedMail::class, function (ClinicRegistrationApprovedMail $mail) use ($clinic) {
            return $mail->clinicName === $clinic->name;
        });

        // Klinik yang disetujui langsung terlihat oleh aplikasi Android.
        $this->getJson('/api/public/clinics')
            ->assertOk()
            ->assertJsonFragment(['slug' => $clinic->slug, 'name' => $clinic->name]);
    }

    public function test_double_approve_creates_no_second_clinic_or_user(): void
    {
        Mail::fake();

        $request = $this->mkPendingRequest();

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/approve')
            ->assertRedirect();

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/approve')
            ->assertNotFound();

        $this->assertSame(1, Clinic::where('slug', $request->requested_clinic_slug)->count());
        $this->assertSame(1, User::where('email', $request->email)->count());
    }

    public function test_approve_fails_clearly_when_slug_was_taken_after_request(): void
    {
        Mail::fake();

        $request = $this->mkPendingRequest();

        // Klinik lain memakai slug tersebut setelah permintaan dibuat.
        Clinic::create([
            'name' => 'Pemakai Slug ' . $this->suffix,
            'slug' => $request->requested_clinic_slug,
            'address' => 'Jl. Pemakai',
        ]);

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/approve')
            ->assertSessionHasErrors('slug');

        // Tidak ada akun/klinik baru; status tetap pending (bisa ditinjau ulang).
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => $request->email]);
        $this->assertSame(1, Clinic::where('slug', $request->requested_clinic_slug)->count());
        Mail::assertNothingSent();
    }

    public function test_reject_stores_note_and_creates_nothing(): void
    {
        Mail::fake();

        $request = $this->mkPendingRequest();

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/reject', [
                'review_note' => 'Alamat klinik belum lengkap.',
            ])
            ->assertRedirect();

        $fresh = $request->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Alamat klinik belum lengkap.', $fresh->review_note);

        $this->assertDatabaseMissing('clinics', ['slug' => $request->requested_clinic_slug]);
        $this->assertDatabaseMissing('users', ['email' => $request->email]);

        Mail::assertSent(ClinicRegistrationRejectedMail::class, function (ClinicRegistrationRejectedMail $mail) {
            return $mail->reason === 'Alamat klinik belum lengkap.';
        });

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/reject')
            ->assertNotFound();
    }

    public function test_requests_list_is_not_scoped_to_current_clinic(): void
    {
        $request = $this->mkPendingRequest();

        // Super admin sedang berada di konteks salah satu klinik.
        $clinic = Clinic::create([
            'name' => 'Konteks ' . $this->suffix,
            'slug' => 'konteks-' . $this->suffix,
            'address' => 'Jl. Konteks',
        ]);

        $this->actingAs($this->superAdmin)
            ->withSession(['current_clinic_id' => $clinic->id])
            ->get('/admin/join-requests?type=new_clinic')
            ->assertOk()
            ->assertSee($request->email, false)
            ->assertSee($request->requested_clinic_name, false);
    }

    // ---------- alur lama tidak berubah ----------

    public function test_legacy_join_flow_still_creates_join_request_for_existing_clinic(): void
    {
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Klinik Existing ' . $this->suffix,
            'slug' => 'klinik-existing-' . $this->suffix,
            'address' => 'Jl. Existing',
            'is_active' => true,
        ]);

        $payload = [
            'registration_type' => ClinicJoinRequest::TYPE_JOIN,
            'clinic_id' => $clinic->id,
            'name' => 'Calon Admin',
            'email' => 'join.' . $this->suffix . '@petheal.test',
            'phone' => '081200000003',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];

        $this->post('/admin/register', $payload)->assertRedirect();
        $this->post('/admin/register/verifikasi', ['code' => str_split($this->sentCode())])
            ->assertRedirect(route('admin.login'));

        $request = ClinicJoinRequest::where('email', $payload['email'])->firstOrFail();
        $this->assertSame(ClinicJoinRequest::TYPE_JOIN, $request->type);
        $this->assertSame($clinic->id, $request->clinic_id);
        $this->assertNull($request->requested_clinic_slug);

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $request->id . '/approve')
            ->assertRedirect();

        $admin = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('clinic_admin', $admin->role);
        $this->assertSame($clinic->id, $admin->clinic_id);

        // Tidak ada klinik tambahan dan email khusus klinik baru tidak dikirim.
        $this->assertSame(1, Clinic::where('id', $clinic->id)->count());
        Mail::assertNotSent(ClinicRegistrationApprovedMail::class);
    }
}
