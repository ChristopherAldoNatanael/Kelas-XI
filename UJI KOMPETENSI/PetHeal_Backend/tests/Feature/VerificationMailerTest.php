<?php

namespace Tests\Feature;

use App\Mail\ClinicRegistrationApprovedMail;
use App\Mail\DoctorInviteMail;
use App\Mail\OtpMail;
use App\Services\OtpService;
use App\Support\VerificationMailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Aturan jalur kirim email verifikasi:
 *
 * - prioritas: Brevo REST API (BREVO_API_KEY) → Brevo SMTP → mailer bawaan;
 * - alamat pengirim dipaksa ke VERIFICATION_MAIL_FROM_ADDRESS bila diisi;
 * - email non-verifikasi dan notifikasi push Firebase tidak ikut berpindah.
 *
 * Catatan penting yang dijaga tes ini: jalur dipilih saat mengirim
 * (`Mail::mailer(...)`), bukan dengan `$mailable->mailer(...)`, karena Laravel
 * menimpa properti itu di Mailer::sendMailable().
 */
class VerificationMailerTest extends TestCase
{
    private function useSmtpCredentials(): void
    {
        Config::set('mail.mailers.brevo.username', '9000001@smtp-brevo.com');
        Config::set('mail.mailers.brevo.password', 'kunci-smtp-uji');
        Config::set('services.brevo.key', null);
        Config::set('mail.verification.from.address', 'verifikasi@petheal.id');
        Config::set('mail.verification.from.name', 'PetHeal Verifikasi');
    }

    private function useApiCredentials(): void
    {
        Config::set('services.brevo.key', 'xkeysib-kunci-uji');
        Config::set('mail.verification.from.address', 'verifikasi@petheal.id');
        Config::set('mail.verification.from.name', 'PetHeal Verifikasi');
    }

    public function test_brevo_configuration_matches_the_official_settings(): void
    {
        $this->assertSame('smtp', config('mail.mailers.brevo.transport'));
        $this->assertSame('smtp-relay.brevo.com', config('mail.mailers.brevo.host'));
        $this->assertSame(587, (int) config('mail.mailers.brevo.port'));
        $this->assertSame('brevo-api', config('mail.mailers.brevo-api.transport'));
    }

    public function test_falls_back_to_the_default_mailer_when_nothing_is_configured(): void
    {
        Config::set('services.brevo.key', null);
        Config::set('mail.mailers.brevo.username', null);
        Config::set('mail.mailers.brevo.password', null);

        $this->assertFalse(VerificationMailer::configured());
        $this->assertFalse(VerificationMailer::apiConfigured());
        $this->assertSame(config('mail.default'), VerificationMailer::name());
    }

    public function test_smtp_relay_is_used_when_only_smtp_credentials_exist(): void
    {
        $this->useSmtpCredentials();

        $this->assertSame('brevo', VerificationMailer::name());

        $otp = (new OtpMail('123456', OtpService::PURPOSE_REGISTER, 10))->build();
        $this->assertSame('verifikasi@petheal.id', $otp->from[0]['address']);
        $this->assertSame('PetHeal Verifikasi', $otp->from[0]['name']);
    }

    public function test_rest_api_is_preferred_over_smtp(): void
    {
        $this->useSmtpCredentials();
        $this->useApiCredentials();

        $this->assertSame('brevo-api', VerificationMailer::name());
    }

    public function test_send_uses_the_selected_mailer_and_reports_success(): void
    {
        $this->useApiCredentials();
        Mail::fake();

        $sent = VerificationMailer::send(
            'tujuan@petheal.test',
            new OtpMail('123456', OtpService::PURPOSE_REGISTER, 10),
            ['purpose' => OtpService::PURPOSE_REGISTER],
        );

        $this->assertTrue($sent);
        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('tujuan@petheal.test'));
    }

    public function test_send_retries_once_then_reports_failure(): void
    {
        $this->useApiCredentials();

        Mail::shouldReceive('mailer')->twice()->with('brevo-api')->andReturnSelf();
        Mail::shouldReceive('to')->twice()->andReturnSelf();
        Mail::shouldReceive('send')->twice()->andThrow(new \RuntimeException('Sender not valid'));

        $sent = VerificationMailer::send(
            'tujuan@petheal.test',
            new OtpMail('123456', OtpService::PURPOSE_REGISTER, 10),
        );

        $this->assertFalse($sent, 'Kegagalan kirim wajib dilaporkan sebagai false.');
    }

    public function test_non_verification_mail_is_not_moved_to_brevo(): void
    {
        $this->useApiCredentials();

        $approved = (new ClinicRegistrationApprovedMail('Klinik Uji', 'Budi', 'https://contoh.test/login'))->build();

        $this->assertEmpty($approved->from, 'Email non-verifikasi tidak boleh ikut memakai pengirim verifikasi.');
    }

    public function test_doctor_invite_uses_the_verified_sender(): void
    {
        $this->useApiCredentials();

        $invite = (new DoctorInviteMail('https://contoh.test/doctor/activate/abc', 'Klinik Uji'))->build();

        $this->assertSame('verifikasi@petheal.id', $invite->from[0]['address']);
    }
}
