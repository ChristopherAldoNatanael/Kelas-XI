<?php

namespace Tests\Feature;

use App\Mail\DoctorInviteMail;
use App\Mail\MailDeliveryCheck;
use App\Mail\OtpMail;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Alat bantu saat user melaporkan "kode OTP tidak masuk".
 *
 * Yang dijaga di sini bukan tampilan, melainkan dua janji:
 *   1. `petheal:mail-test` benar-benar mengirim email uji lewat mailer aktif
 *      dan jujur melaporkan kegagalan;
 *   2. endpoint OTP memberi tahu aplikasi bila kode terbit tetapi email gagal
 *      terkirim (`mail_sent = false`), supaya aplikasi tidak menyuruh user
 *      menunggu email yang tidak akan pernah datang.
 */
class OtpMailDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_mail_test_command_sends_a_delivery_check_email(): void
    {
        Mail::fake();

        $this->artisan('petheal:mail-test', ['email' => 'tujuan.uji@petheal.test'])
            ->assertSuccessful();

        Mail::assertSent(MailDeliveryCheck::class, function (MailDeliveryCheck $mail) {
            return $mail->hasTo('tujuan.uji@petheal.test')
                && $mail->fromAddress === config('mail.from.address');
        });
    }

    public function test_mail_test_command_rejects_an_invalid_address(): void
    {
        Mail::fake();

        $this->artisan('petheal:mail-test', ['email' => 'bukan-email'])
            ->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_mail_test_command_reports_failure_and_hides_the_password(): void
    {
        Mail::shouldReceive('mailer')->andReturnSelf();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('535 Username and Password not accepted'));

        $this->artisan('petheal:mail-test', ['email' => 'tujuan.uji@petheal.test'])
            ->expectsOutputToContain('GAGAL')
            ->doesntExpectOutputToContain((string) config('mail.mailers.smtp.password'))
            ->assertFailed();
    }

    public function test_mail_test_command_can_send_the_real_otp_template(): void
    {
        Mail::fake();

        $this->artisan('petheal:mail-test', [
            'email' => 'tujuan.uji@petheal.test',
            '--template' => 'otp',
        ])->assertSuccessful();

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('tujuan.uji@petheal.test'));
    }

    public function test_mail_test_command_can_send_the_doctor_invite_template(): void
    {
        Mail::fake();

        $this->artisan('petheal:mail-test', [
            'email' => 'tujuan.uji@petheal.test',
            '--template' => 'doctor',
        ])->assertSuccessful();

        Mail::assertSent(DoctorInviteMail::class, function (DoctorInviteMail $mail) {
            return $mail->hasTo('tujuan.uji@petheal.test')
                && str_contains($mail->activationUrl, '/doctor/activate/');
        });
    }

    /**
     * Uji A/B filter spam: subjek harus bisa ditimpa tanpa mengganti template,
     * supaya bisa dipisahkan apakah yang ditahan Gmail itu subjek atau isi.
     */
    public function test_mail_test_command_can_override_the_subject(): void
    {
        Mail::fake();

        $this->artisan('petheal:mail-test', [
            'email' => 'tujuan.uji@petheal.test',
            '--template' => 'otp',
            '--subject' => 'Subjek uji A/B',
        ])->assertSuccessful();

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->subject === 'Subjek uji A/B');
    }

    public function test_mail_test_command_rejects_an_unknown_template(): void
    {
        Mail::fake();

        $this->artisan('petheal:mail-test', [
            'email' => 'tujuan.uji@petheal.test',
            '--template' => 'sembarang',
        ])->assertFailed();

        Mail::assertNothingSent();
    }

    /**
     * Kode OTP tidak boleh berada di teks tersembunyi (preheader): filter spam
     * Gmail menandai teks tak terlihat yang memuat kode sebagai pola phishing,
     * dan pratinjau notifikasi ponsel akan menampilkan kode di layar kunci.
     */
    public function test_otp_email_never_repeats_the_code_in_hidden_text(): void
    {
        $html = (new OtpMail('918273', OtpService::PURPOSE_REGISTER, 10))->render();

        $this->assertSame(1, substr_count($html, '918273'), 'Kode muncul lebih dari sekali di HTML email.');
    }

    public function test_admin_otp_email_never_repeats_the_code_in_hidden_text(): void
    {
        $html = (new OtpMail('918273', OtpService::PURPOSE_ADMIN_REGISTER, 10, 'Klinik Sehat', 'Budi'))->render();

        $this->assertSame(1, substr_count($html, '918273'), 'Kode muncul lebih dari sekali di HTML email admin.');
    }

    /**
     * Subjek email verifikasi tidak boleh berpola "Kode OTP ...".
     *
     * Bukti uji A/B: subjek "Kode OTP pendaftaran PetHeal" ditahan Gmail,
     * sedangkan isi template OTP yang sama lolos saat subjeknya netral.
     * Tes ini menjaga agar subjek tidak kembali ke pola lama saat template
     * disunting.
     */
    public function test_no_verification_email_uses_a_code_otp_subject(): void
    {
        $purposes = [
            OtpService::PURPOSE_REGISTER,
            OtpService::PURPOSE_RESET,
            OtpService::PURPOSE_DELETE_ACCOUNT,
            OtpService::PURPOSE_EMAIL_CHANGE,
            OtpService::PURPOSE_ADMIN_REGISTER,
            OtpService::PURPOSE_ADMIN_PASSWORD,
            OtpService::PURPOSE_DOCTOR_PASSWORD,
        ];

        foreach ($purposes as $purpose) {
            $mailable = (new OtpMail('123456', $purpose, 10, 'Klinik Uji', 'Budi'))->build();
            $subject = (string) $mailable->subject;

            $this->assertStringNotContainsStringIgnoringCase('kode otp', $subject, "Subjek {$purpose} memakai pola 'Kode OTP'.");
            $this->assertStringNotContainsStringIgnoringCase('otp', $subject, "Subjek {$purpose} masih memuat 'OTP'.");
            $this->assertStringStartsWith('PetHeal', $subject, "Subjek {$purpose} tidak diawali nama aplikasi.");
        }
    }

    public function test_otp_status_command_shows_an_active_code(): void
    {
        Mail::fake();

        $email = 'status.' . strtolower(Str::random(6)) . '@petheal.test';

        /** @var OtpService $otp */
        $otp = app(OtpService::class);
        $code = $otp->issue($email, OtpService::PURPOSE_REGISTER, []);

        $this->assertSame(6, strlen($code));
        $this->assertSame(1, DB::table('otp_codes')->where('email', $email)->count());

        $this->artisan('petheal:otp-status', ['email' => $email])
            ->expectsOutputToContain('AKTIF')
            ->assertSuccessful();
    }

    public function test_otp_status_command_says_when_no_code_exists(): void
    {
        $this->artisan('petheal:otp-status', ['email' => 'kosong.' . strtolower(Str::random(6)) . '@petheal.test'])
            ->expectsOutputToContain('Tidak ada kode OTP')
            ->assertSuccessful();
    }

    public function test_register_otp_endpoint_reports_when_the_email_could_not_be_sent(): void
    {
        // Mailer dibuat gagal: ini kondisi nyata saat kredensial SMTP dicabut.
        Mail::shouldReceive('mailer')->andReturnSelf();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('535 auth failed'));

        $email = 'gagal.' . strtolower(Str::random(6)) . '@petheal.test';

        $response = $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Calon Pemilik',
            'email' => $email,
            'password' => 'rahasia12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('mail_sent', false)
            ->assertJsonPath('data.email', $email);

        // Kode tetap terbit di server, jadi user bisa mencoba kirim ulang.
        $this->assertSame(1, DB::table('otp_codes')
            ->where('email', $email)
            ->where('purpose', OtpService::PURPOSE_REGISTER)
            ->count());
    }

    public function test_register_otp_endpoint_reports_successful_delivery(): void
    {
        Mail::fake();

        $email = 'sukses.' . strtolower(Str::random(6)) . '@petheal.test';

        $this->postJson('/api/auth/register/request-otp', [
            'name' => 'Calon Pemilik',
            'email' => $email,
            'password' => 'rahasia12345',
        ])
            ->assertOk()
            ->assertJsonPath('mail_sent', true);

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo($email));
    }
}
