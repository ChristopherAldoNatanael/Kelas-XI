<?php

namespace Tests\Feature;

use App\Mail\DoctorInviteMail;
use App\Mail\OtpMail;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Undangan dokter tanpa OTP: satu tautan email -> langsung buat kata sandi.
 *
 * Token di tautan disimpan sebagai hash, berlaku 60 menit, dan hangus setelah
 * dipakai. Test ini memastikan alurnya benar sekaligus tidak membuka celah
 * aktivasi ulang lewat tautan yang sama.
 */
class DoctorInviteMailTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;
    private User $admin;
    private Doctor $doctor;
    private string $doctorEmail;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Klinik Undangan ' . $this->suffix,
            'slug' => 'klinik-undangan-' . $this->suffix,
            'address' => 'Jl. Undangan',
            'is_active' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Admin Undangan',
            'email' => 'admin-invite-' . $this->suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);
        $this->doctorEmail = 'dokter-' . $this->suffix . '@test.local';
        $this->doctor = Doctor::create([
            'name' => 'Dr Undangan ' . $this->suffix,
            'specialization' => 'Umum',
            'email' => $this->doctorEmail,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    /** Kirim undangan dan kembalikan URL aktivasi dari email. */
    private function sendInvite(): string
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post('/admin/doctors/' . $this->doctor->id . '/invite')
            ->assertRedirect();

        /** @var DoctorInviteMail $mail */
        $mail = Mail::sent(DoctorInviteMail::class)->firstOrFail();

        return $mail->activationUrl;
    }

    public function test_invite_email_has_direct_password_link_without_otp(): void
    {
        $url = $this->sendInvite();

        Mail::assertSent(DoctorInviteMail::class, function (DoctorInviteMail $mail) use ($url) {
            return $mail->activationUrl === $url
                && str_contains($url, '/doctor/activate/')
                && str_contains($mail->render(), $url);
        });

        // Tidak ada email OTP untuk undangan dokter lagi.
        Mail::assertNotSent(OtpMail::class);

        $this->assertNotNull($this->doctor->fresh()->invite_token);
        $this->assertTrue($this->doctor->fresh()->invite_token_expires_at->isFuture());

        $this->get($url)
            ->assertOk()
            ->assertSee($this->doctorEmail)
            ->assertSee('Buat kata sandi portal dokter')
            ->assertSee('Aktivasi Akun & Masuk Dashboard')
            ->assertDontSee('name="code[]"', false);

        // Panel admin menampilkan status tautan yang masih aktif.
        $this->actingAs($this->admin)->get('/admin/doctors/' . $this->doctor->id)
            ->assertOk()
            ->assertSee('Tautan undangan aktif sampai');
    }

    public function test_link_activates_account_with_password_only(): void
    {
        $url = $this->sendInvite();

        $this->get($url)->assertOk();

        $this->post($url, [
            'password' => 'rahasia-dokter-123',
            'password_confirmation' => 'rahasia-dokter-123',
        ])->assertRedirect(route('doctor.welcome'));

        $user = User::where('email', $this->doctorEmail)->firstOrFail();
        $this->assertSame('doctor', $user->role);
        $this->assertSame($this->clinic->id, $user->clinic_id);
        $this->assertTrue(Hash::check('rahasia-dokter-123', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($user->id, $this->doctor->fresh()->user_id);
        $this->assertNull($this->doctor->fresh()->invite_token);
        $this->assertAuthenticatedAs($user);

        // Layar sukses menampilkan nama + tombol menuju dashboard.
        $this->get(route('doctor.welcome'))
            ->assertOk()
            ->assertSee('Akun dokter Anda sudah aktif')
            ->assertSee($user->name)
            ->assertSee('Buka Dashboard Dokter');

        // Flash sekali pakai: refresh layar sukses langsung ke dashboard.
        $this->get(route('doctor.welcome'))->assertRedirect(route('doctor.dashboard'));
        $this->get(route('doctor.dashboard'))->assertOk();

        // Setelah logout, kredensial baru bisa dipakai di halaman login dokter.
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->post('/doctor/login', [
            'email' => $this->doctorEmail,
            'password' => 'rahasia-dokter-123',
        ])->assertRedirect(route('doctor.dashboard'));
    }

    public function test_link_is_single_use(): void
    {
        $url = $this->sendInvite();

        $this->get($url)->assertOk();
        $this->post($url, [
            'password' => 'rahasia-dokter-123',
            'password_confirmation' => 'rahasia-dokter-123',
        ])->assertRedirect(route('doctor.welcome'));

        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get($url)
            ->assertRedirect(route('doctor.activate'))
            ->assertSessionHasErrors('email');

        $this->post($url, [
            'password' => 'password-kedua-123',
            'password_confirmation' => 'password-kedua-123',
        ])->assertRedirect(route('doctor.activate'));

        $this->assertSame(1, User::where('email', $this->doctorEmail)->count());
        $this->assertTrue(Hash::check('rahasia-dokter-123', User::where('email', $this->doctorEmail)->first()->password));
    }

    public function test_expired_link_is_rejected(): void
    {
        $url = $this->sendInvite();

        $this->doctor->forceFill(['invite_token_expires_at' => Carbon::now()->subMinute()])->save();

        $this->get($url)
            ->assertRedirect(route('doctor.activate'))
            ->assertSessionHasErrors('email');

        $this->from(route('doctor.activate'))->post($url, [
            'password' => 'rahasia-dokter-123',
            'password_confirmation' => 'rahasia-dokter-123',
        ])->assertRedirect(route('doctor.activate'));

        $this->assertNull(User::where('email', $this->doctorEmail)->first());
    }

    public function test_password_confirmation_must_match(): void
    {
        $url = $this->sendInvite();

        $this->get($url)->assertOk();

        $this->post($url, [
            'password' => 'rahasia-dokter-123',
            'password_confirmation' => 'beda-sendiri-123',
        ])->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', $this->doctorEmail)->first());
        $this->assertNotNull($this->doctor->fresh()->invite_token);

        // Tautan tetap bisa dipakai setelah kesalahan input.
        $this->get($url)->assertOk();
    }

    public function test_reinvite_rotates_the_token(): void
    {
        $firstUrl = $this->sendInvite();
        $firstToken = $this->doctor->fresh()->invite_token;

        $secondUrl = $this->sendInvite();

        $this->assertNotSame($firstUrl, $secondUrl);
        $this->assertNotSame($firstToken, $this->doctor->fresh()->invite_token);

        // Tautan lama langsung mati setelah undangan baru dibuat.
        $this->get($firstUrl)->assertRedirect(route('doctor.activate'));
        $this->get($secondUrl)->assertOk();
    }

    public function test_changing_doctor_email_invalidates_pending_link(): void
    {
        $url = $this->sendInvite();

        $this->actingAs($this->admin)
            ->put('/admin/doctors/' . $this->doctor->id, [
                'name' => $this->doctor->name,
                'specialization' => 'Umum',
                'phone' => '081200000000',
                'email' => 'email-baru-' . $this->suffix . '@test.local',
                'available_days' => ['monday'],
                'start_time' => '08:00',
                'end_time' => '17:00',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.doctors.index'));

        $this->assertNull($this->doctor->fresh()->invite_token);
        $this->get($url)->assertRedirect(route('doctor.activate'));
    }

    public function test_notice_page_and_login_link_point_to_activation(): void
    {
        $this->get('/doctor/activate')
            ->assertOk()
            ->assertSee('Buka tautan di email undangan')
            ->assertDontSee('name="code[]"', false);

        $this->get('/doctor/login')
            ->assertOk()
            ->assertSee(route('doctor.activate'));
    }

    public function test_invite_is_clinic_scoped(): void
    {
        $otherClinic = Clinic::create([
            'name' => 'Klinik Lain ' . $this->suffix,
            'slug' => 'klinik-lain-' . $this->suffix,
            'address' => 'Jl. Lain',
            'is_active' => true,
        ]);
        $otherAdmin = User::create([
            'name' => 'Admin Lain',
            'email' => 'admin-lain-' . $this->suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $otherClinic->id,
        ]);

        Mail::fake();

        $this->actingAs($otherAdmin)
            ->post('/admin/doctors/' . $this->doctor->id . '/invite')
            ->assertNotFound();

        Mail::assertNothingSent();
        $this->assertNull($this->doctor->fresh()->invite_token);
    }
}
