<?php

namespace Tests\Feature;

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
 * Kode verifikasi dashboard admin:
 * - pendaftaran admin tidak membuat request sebelum email terbukti;
 * - ganti kata sandi tidak menyimpan apa pun tanpa kode email;
 * - teks email admin berbeda dari email OTP aplikasi pet owner.
 */
class AdminOtpFlowTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'OTP Clinic ' . $this->suffix,
            'slug' => 'otp-clinic-' . $this->suffix,
            'address' => 'Jl. Verifikasi',
            'primary_color' => '#14794B',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin OTP',
            'email' => 'otp.admin.' . $this->suffix . '@petheal.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);
    }

    private function joinPayload(array $overrides = []): array
    {
        return array_merge([
            'clinic_id' => $this->clinic->id,
            'name' => 'Calon Admin',
            'email' => 'calon.' . $this->suffix . '@petheal.test',
            'phone' => '081200000000',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    private function sentCode(): string
    {
        return Mail::sent(OtpMail::class)->firstOrFail()->code;
    }

    public function test_register_sends_admin_code_before_creating_request(): void
    {
        Mail::fake();

        $payload = $this->joinPayload();

        $this->post('/admin/register', $payload)
            ->assertRedirect(route('admin.register'));

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($payload) {
            return $mail->purpose === 'admin_register'
                && $mail->clinicName === $this->clinic->name
                && str_contains(strtolower((string) $mail->build()->subject), 'admin');
        });

        // Belum ada request dan belum ada akun sebelum kode diverifikasi.
        $this->assertDatabaseMissing('clinic_join_requests', ['email' => $payload['email']]);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);

        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('Verifikasi email admin')
            ->assertSee($payload['email'], false);
    }

    public function test_register_wizard_renders_before_code_requested(): void
    {
        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('Pilih klinik yang ingin Anda kelola')
            ->assertSee('Kirim kode verifikasi');
    }

    public function test_resend_respects_cooldown_then_sends_new_code(): void
    {
        Mail::fake();

        $payload = $this->joinPayload();
        $this->post('/admin/register', $payload)->assertRedirect();
        Mail::assertSent(OtpMail::class, 1);

        $this->post('/admin/register/kirim-ulang')->assertSessionHas('warning');
        Mail::assertSent(OtpMail::class, 1);

        $this->withSession(['admin_join_otp_sent_at' => now()->subSeconds(61)->timestamp])
            ->post('/admin/register/kirim-ulang')
            ->assertSessionHas('success');

        Mail::assertSent(OtpMail::class, 2);
    }

    public function test_admin_email_copy_differs_from_pet_app_copy(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post('/admin/settings/password/otp')
            ->assertRedirect();

        /** @var OtpMail $mail */
        $mail = Mail::sent(OtpMail::class)->firstOrFail();
        $built = $mail->build();

        $this->assertSame('emails.otp-admin', $built->view);
        $this->assertStringContainsString('admin', strtolower((string) $built->subject));

        $html = $mail->render();
        $this->assertStringContainsString('Kode akses', $html);
        $this->assertStringContainsString('dashboard admin', $html);
        // Frasa khas email OTP aplikasi pet owner tidak boleh muncul di sini.
        $this->assertStringNotContainsString('di aplikasi untuk menyelesaikan pendaftaran', $html);
    }

    public function test_wrong_code_keeps_request_uncreated(): void
    {
        Mail::fake();

        $payload = $this->joinPayload();
        $this->post('/admin/register', $payload)->assertRedirect();

        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post('/admin/register/verifikasi', ['code' => str_split($wrong)])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('clinic_join_requests', ['email' => $payload['email']]);
    }

    public function test_verified_code_creates_join_request_once(): void
    {
        Mail::fake();

        $payload = $this->joinPayload();
        $this->post('/admin/register', $payload)->assertRedirect();
        $code = $this->sentCode();

        $this->post('/admin/register/verifikasi', ['code' => str_split($code)])
            ->assertRedirect(route('admin.login'));

        $request = ClinicJoinRequest::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame($this->clinic->id, $request->clinic_id);
        $this->assertTrue(Hash::check('rahasia123', $request->password_hash));

        // Kode sekali pakai: percobaan kedua tidak membuat request tambahan.
        $this->post('/admin/register/verifikasi', ['code' => str_split($code)])
            ->assertRedirect(route('admin.register'));

        $this->assertSame(1, ClinicJoinRequest::where('email', $payload['email'])->count());
    }

    public function test_registered_email_is_rejected_before_sending_code(): void
    {
        Mail::fake();

        $this->post('/admin/register', $this->joinPayload(['email' => $this->admin->email]))
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_password_change_requires_code_and_current_password(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post('/admin/settings/password', [
                'current_password' => 'password123',
                'new_password' => 'passwordBaru123',
                'new_password_confirmation' => 'passwordBaru123',
            ])
            ->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password123', $this->admin->fresh()->password));
    }

    public function test_password_change_with_valid_code_updates_password_and_is_single_use(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post('/admin/settings/password/otp')
            ->assertRedirect();

        $code = $this->sentCode();

        $this->actingAs($this->admin)
            ->post('/admin/settings/password', [
                'current_password' => 'password123',
                'new_password' => 'passwordBaru123',
                'new_password_confirmation' => 'passwordBaru123',
                'code' => str_split($code),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('passwordBaru123', $this->admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password.change']);

        // Kode yang sama tidak bisa dipakai untuk perubahan kedua.
        $this->actingAs($this->admin)
            ->post('/admin/settings/password', [
                'current_password' => 'passwordBaru123',
                'new_password' => 'passwordKetiga123',
                'new_password_confirmation' => 'passwordKetiga123',
                'code' => str_split($code),
            ])
            ->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('passwordBaru123', $this->admin->fresh()->password));
    }

    public function test_security_tab_switches_from_send_button_to_code_form(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/settings?tab=keamanan')
            ->assertOk()
            ->assertSee('Minta kode akses')
            ->assertDontSee('Simpan password baru');

        Mail::fake();
        $this->actingAs($this->admin)
            ->post('/admin/settings/password/otp')
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get('/admin/settings?tab=keamanan')
            ->assertOk()
            ->assertSee('Kode akses admin')
            ->assertSee('Simpan password baru')
            ->assertSee($this->admin->email, false);
    }
}
