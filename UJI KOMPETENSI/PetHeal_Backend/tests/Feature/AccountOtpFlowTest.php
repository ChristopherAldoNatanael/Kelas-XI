<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Clinic;
use App\Models\Notification;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\WeightRecord;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifikasi email untuk dua aksi akun di aplikasi pet owner:
 * hapus akun dan ganti email. Keduanya memakai tabel otp_codes yang sama
 * dengan pendaftaran/reset sandi, tetapi tujuannya terpisah.
 */
class AccountOtpFlowTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Mail::fake();

        $this->suffix = strtolower(Str::random(6));
        $this->clinic = Clinic::create([
            'name' => 'Akun OTP ' . $this->suffix,
            'slug' => 'akun-otp-' . $this->suffix,
            'address' => 'Jl. Verifikasi Akun',
            'primary_color' => '#18C964',
            'is_active' => true,
        ]);
    }

    private function makeUser(string $tag): User
    {
        return User::create([
            'name' => 'Pemilik ' . $tag,
            'email' => $tag . '.' . $this->suffix . '@petheal.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinic->id,
            'email_verified_at' => now(),
        ]);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->createToken('account-otp-test')->plainTextToken,
            'Accept' => 'application/json',
        ]);
    }

    private function codeFor(string $purpose, string $email): string
    {
        /** @var OtpMail $mail */
        $mail = Mail::sent(OtpMail::class)
            ->first(fn (OtpMail $sent) => $sent->purpose === $purpose && $sent->hasTo($email));

        $this->assertNotNull($mail, "Email OTP tujuan {$purpose} untuk {$email} tidak terkirim.");

        return $mail->code;
    }

    public function test_delete_account_with_data_is_blocked_until_code_verified(): void
    {
        $user = $this->makeUser('hapus');
        Pet::create([
            'user_id' => $user->id,
            'clinic_id' => $this->clinic->id,
            'name' => 'Mochi ' . $this->suffix,
            'species' => 'cat',
            'gender' => 'male',
        ]);

        $this->apiAs($user)->deleteJson('/api/auth/account')
            ->assertStatus(428)
            ->assertJsonPath('code', 'delete_verification_required');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('pets', ['user_id' => $user->id]);
    }

    public function test_delete_account_after_code_removes_user_and_pets(): void
    {
        $user = $this->makeUser('bersih');
        $pet = Pet::create([
            'user_id' => $user->id,
            'clinic_id' => $this->clinic->id,
            'name' => 'Kopi ' . $this->suffix,
            'species' => 'cat',
            'gender' => 'female',
        ]);
        WeightRecord::create([
            'pet_id' => $pet->id,
            'weight' => 4.25,
            'recorded_at' => now()->toDateString(),
        ]);
        Vaccination::create([
            'pet_id' => $pet->id,
            'vaccine_name' => 'Rabies',
            'date_administered' => now()->toDateString(),
        ]);
        Notification::create([
            'user_id' => $user->id,
            'clinic_id' => $this->clinic->id,
            'title' => 'Halo ' . $this->suffix,
            'body' => 'Notifikasi milik akun yang akan dihapus.',
            'type' => 'system',
        ]);

        $response = $this->apiAs($user)->postJson('/api/auth/account/delete/request-otp')
            ->assertOk()
            ->assertJsonPath('success', true);

        $masked = $response->json('data.email');
        $this->assertNotSame($user->email, $masked);
        $this->assertStringContainsString('@petheal.test', $masked);
        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->purpose === 'delete_account'
            && $mail->hasTo($user->email));

        $code = $this->codeFor('delete_account', $user->email);

        $this->apiAs($user)->postJson('/api/auth/account/delete/verify-otp', ['code' => '000000'])
            ->assertStatus(400);

        $this->apiAs($user)->postJson('/api/auth/account/delete/verify-otp', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->apiAs($user)->deleteJson('/api/auth/account')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted.pets', 1);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('pets', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('weight_records', ['pet_id' => $pet->id]);
        $this->assertDatabaseMissing('vaccinations', ['pet_id' => $pet->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.self_delete',
            'model_id' => $user->id,
        ]);
    }

    public function test_empty_account_can_still_be_deleted_without_code(): void
    {
        $user = $this->makeUser('kosong');

        $this->apiAs($user)->deleteJson('/api/auth/account')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Mail::assertNothingSent();
    }

    public function test_email_change_sends_code_to_new_address_then_persists_it(): void
    {
        $user = $this->makeUser('ganti');
        $newEmail = 'baru.' . $this->suffix . '@petheal.test';

        $this->apiAs($user)->postJson('/api/auth/email/change/request-otp', ['email' => $newEmail])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->purpose === 'email_change'
            && $mail->hasTo($newEmail));

        $code = $this->codeFor('email_change', $newEmail);

        $this->apiAs($user)->postJson('/api/auth/email/change/verify-otp', [
            'email' => $newEmail,
            'code' => $code,
        ])->assertOk()->assertJsonPath('data.email', $newEmail);

        $updated = $user->fresh();
        $this->assertSame($newEmail, $updated->email);
        $this->assertNotNull($updated->email_verified_at);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $newEmail]);
    }

    public function test_email_change_rejects_same_and_taken_address(): void
    {
        $user = $this->makeUser('sama');
        $other = $this->makeUser('lain');

        $this->apiAs($user)->postJson('/api/auth/email/change/request-otp', ['email' => $user->email])
            ->assertStatus(422)
            ->assertJsonPath('code', 'same_email');

        $this->apiAs($user)->postJson('/api/auth/email/change/request-otp', ['email' => $other->email])
            ->assertStatus(409)
            ->assertJsonPath('code', 'email_taken');

        Mail::assertNothingSent();
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_email_change_wrong_code_keeps_old_email(): void
    {
        $user = $this->makeUser('salah');
        $newEmail = 'kodebaru.' . $this->suffix . '@petheal.test';

        $this->apiAs($user)->postJson('/api/auth/email/change/request-otp', ['email' => $newEmail])
            ->assertOk();

        $this->apiAs($user)->postJson('/api/auth/email/change/verify-otp', [
            'email' => $newEmail,
            'code' => '000000',
        ])->assertStatus(400);

        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame(0, DB::table('otp_codes')
            ->where('email', $newEmail)
            ->whereNotNull('consumed_at')
            ->count());
    }
}
