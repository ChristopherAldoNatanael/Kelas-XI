<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nomor WhatsApp yang diisi di form daftar harus benar-benar tersimpan.
 *
 * Nomor itu dipakai admin klinik untuk menghubungi pasien saat jadwal berubah
 * atau dana booking harus dikembalikan; kalau hilang di tengah alur OTP, fitur
 * refund tidak punya kontak sama sekali. Pasien boleh mendaftar tanpa nomor
 * (akun Google lama), jadi field ini tetap opsional di server.
 */
class RegisterPhoneCaptureTest extends TestCase
{
    use DatabaseTransactions;

    private function codeFor(string $email): string
    {
        /** @var OtpMail $mail */
        $mail = Mail::sent(OtpMail::class)
            ->first(fn (OtpMail $sent) => $sent->hasTo($email));

        $this->assertNotNull($mail, "Email OTP untuk {$email} tidak terkirim.");

        return $mail->code;
    }

    public function test_phone_from_register_form_is_stored_on_the_new_account(): void
    {
        Mail::fake();

        $email = 'nomor.' . Str::lower(Str::random(6)) . '@petheal.test';

        $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Pemilik Berdasi',
            'email' => $email,
            'password' => 'rahasia12345',
            'phone' => '081234567890',
        ])->assertOk()->assertJsonPath('mail_sent', true);

        $this->postJson('/api/auth/register/verify-otp', [
            'email' => $email,
            'code' => $this->codeFor($email),
        ])->assertOk()->assertJsonPath('success', true);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertSame('081234567890', $user->phone);
    }

    public function test_registration_without_phone_still_works(): void
    {
        Mail::fake();

        $email = 'tanpa.nomor.' . Str::lower(Str::random(6)) . '@petheal.test';

        $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Pemilik Tanpa Nomor',
            'email' => $email,
            'password' => 'rahasia12345',
        ])->assertOk();

        $this->postJson('/api/auth/register/verify-otp', [
            'email' => $email,
            'code' => $this->codeFor($email),
        ])->assertOk();

        $this->assertNull(User::where('email', $email)->firstOrFail()->phone);
    }

    public function test_phone_can_be_added_later_through_profile_update(): void
    {
        // Jalur yang dipakai akun Google: nomor menyusul lewat layar pengisian
        // nomor, yang memanggil PUT /api/auth/profile.
        $user = User::create([
            'name' => 'Akun Google',
            'email' => 'google.' . Str::lower(Str::random(6)) . '@petheal.test',
            'password' => bcrypt('rahasia12345'),
            'role' => 'user',
            'phone' => null,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->createToken('phone-capture')->plainTextToken,
            'Accept' => 'application/json',
        ])->putJson('/api/auth/profile', [
            'name' => 'Akun Google',
            'phone' => '081298765432',
        ])->assertOk();

        $this->assertSame('081298765432', $user->fresh()->phone);
    }
}
