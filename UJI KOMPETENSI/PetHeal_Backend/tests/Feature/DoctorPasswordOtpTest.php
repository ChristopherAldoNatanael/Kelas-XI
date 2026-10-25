<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ganti kata sandi portal dokter: tanpa kata sandi lama, wajib kode email.
 *
 * Alur yang diuji:
 * 1. dokter mengisi kata sandi baru -> kode 6 digit dikirim ke email akun;
 * 2. kata sandi BELUM berubah sebelum kode diverifikasi;
 * 3. kode benar -> hash yang sudah disiapkan dipakai, kode hangus;
 * 4. kode milik akun lain / sesi kedaluwarsa tidak bisa dipakai.
 */
class DoctorPasswordOtpTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $doctorUser;
    private User $otherDoctorUser;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'OTP Dokter ' . $suffix,
            'slug' => 'otp-dokter-' . $suffix,
            'address' => 'Jl. OTP Dokter',
            'is_active' => true,
        ]);

        $this->doctorUser = $this->makeDoctorUser('dr-otp-a', $suffix);
        $this->otherDoctorUser = $this->makeDoctorUser('dr-otp-b', $suffix);
    }

    public function test_password_is_saved_only_after_email_code_is_verified(): void
    {
        Mail::fake();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Kode terkirim ke email akun, bukan ditampilkan di halaman.
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            return $mail->purpose === OtpService::PURPOSE_DOCTOR_PASSWORD
                && $mail->code !== '';
        });

        // Belum ada perubahan sebelum kode diverifikasi.
        $this->assertTrue(Hash::check('password123', $this->doctorUser->fresh()->password));

        // Halaman berpindah ke langkah 2 (kolom kode) tanpa error Blade.
        $this->actingAs($this->doctorUser)->get('/doctor/profile')
            ->assertOk()
            ->assertSee('Kode verifikasi')
            ->assertSee('Batalkan');

        $code = $this->lastSentCode();

        $this->actingAs($this->doctorUser)->put('/doctor/profile/password', [
            'code' => str_split($code),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandi-baru-123', $this->doctorUser->fresh()->password));
        $this->assertNull(DB::table('otp_codes')->where('purpose', 'doctor_password')->first());

        // Kode sekali pakai: percobaan kedua ditolak.
        $this->actingAs($this->doctorUser)->put('/doctor/profile/password', [
            'code' => str_split($code),
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('sandi-baru-123', $this->doctorUser->fresh()->password));
    }

    public function test_wrong_code_does_not_change_password(): void
    {
        Mail::fake();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('password123', $this->doctorUser->fresh()->password));

        $code = $this->lastSentCode();
        $wrong = ((int) $code[0] + 1) % 10 . substr($code, 1);

        $this->actingAs($this->doctorUser)->put('/doctor/profile/password', [
            'code' => str_split($wrong),
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password123', $this->doctorUser->fresh()->password));
        $this->assertSame(1, (int) DB::table('otp_codes')->where('purpose', 'doctor_password')->value('attempts'));
    }

    public function test_short_or_unconfirmed_password_does_not_send_code(): void
    {
        Mail::fake();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'beda-sandi-123',
        ])->assertSessionHasErrors('password');

        Mail::assertNothingSent();
        $this->assertTrue(Hash::check('password123', $this->doctorUser->fresh()->password));
    }

    public function test_resend_does_not_require_retyping_new_password(): void
    {
        Mail::fake();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Kirim ulang hanya menekan tombol, tanpa kolom sandi.
        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp')
            ->assertRedirect()->assertSessionHasNoErrors();

        Mail::assertSent(OtpMail::class, 2);

        $this->actingAs($this->doctorUser)->put('/doctor/profile/password', [
            'code' => str_split($this->lastSentCode()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandi-baru-123', $this->doctorUser->fresh()->password));
    }

    public function test_pending_code_from_another_account_cannot_be_used(): void
    {
        Mail::fake();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect();

        $code = $this->lastSentCode();

        $this->actingAs($this->otherDoctorUser)->put('/doctor/profile/password', [
            'code' => str_split($code),
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password123', $this->doctorUser->fresh()->password));
        $this->assertTrue(Hash::check('password123', $this->otherDoctorUser->fresh()->password));
    }

    public function test_cancel_verification_clears_pending_password(): void
    {
        Mail::fake();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp', [
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->doctorUser)->post('/doctor/profile/password/otp/cancel')
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(DB::table('otp_codes')->where('purpose', 'doctor_password')->first());

        // Setelah dibatalkan, halaman kembali ke langkah 1 dan tidak ada
        // kata sandi yang tersimpan.
        $this->actingAs($this->doctorUser)->get('/doctor/profile')
            ->assertOk()
            ->assertSee('Kirim Kode Verifikasi');

        $this->assertTrue(Hash::check('password123', $this->doctorUser->fresh()->password));
    }

    private function makeDoctorUser(string $tag, string $suffix): User
    {
        $user = User::create([
            'name' => $tag . ' ' . $suffix,
            'email' => $tag . $suffix . '@otp.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        Doctor::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'specialization' => 'Umum',
            'email' => $user->email,
            'phone' => '081200000000',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        return $user;
    }

    /** Kode dari email terakhir — OTP baru menimpa kode sebelumnya. */
    private function lastSentCode(): string
    {
        $codes = [];

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$codes) {
            $codes[] = $mail->code;

            return true;
        });

        $this->assertNotEmpty($codes);

        return (string) end($codes);
    }
}
