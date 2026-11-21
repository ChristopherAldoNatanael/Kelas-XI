<?php

namespace Tests\Feature;

use App\Support\Mail\BrevoApiTransport;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Transport Brevo lewat REST API.
 *
 * Dipakai karena SMTP relay gagal dengan 535 saat login/key tidak cocok, dan
 * kegagalan itu tidak terlihat dari aplikasi. Test ini mengunci bentuk payload
 * yang diterima Brevo serta penerusan pesan error aslinya.
 */
class BrevoApiTransportTest extends TestCase
{
    public function test_it_posts_the_rendered_message_and_returns_the_message_id(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<2026.petheal@smtp-relay.brevo.com>'], 201),
        ]);

        $email = (new Email())
            ->from(new \Symfony\Component\Mime\Address('verifikasi@petheal.id', 'PetHeal'))
            ->to(new \Symfony\Component\Mime\Address('pemilik@contoh.test', 'Pemilik'))
            ->subject('PetHeal: pendaftaran akun Anda')
            ->text('Kode Anda 123456')
            ->html('<p>Kode Anda <b>123456</b></p>');

        $sent = (new BrevoApiTransport('xkeysib-uji'))->send($email);

        $this->assertSame('<2026.petheal@smtp-relay.brevo.com>', $sent?->getMessageId());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'xkeysib-uji')
                && $request['sender']['email'] === 'verifikasi@petheal.id'
                && $request['to'][0]['email'] === 'pemilik@contoh.test'
                && $request['subject'] === 'PetHeal: pendaftaran akun Anda'
                && str_contains($request['htmlContent'], '123456')
                && $request['textContent'] === 'Kode Anda 123456';
        });
    }

    public function test_it_surfaces_the_brevo_error_message(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['message' => 'Sender not valid'], 400),
        ]);

        $email = (new Email())
            ->from('belum.terverifikasi@contoh.test')
            ->to('pemilik@contoh.test')
            ->subject('Uji')
            ->text('Uji');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Sender not valid');

        (new BrevoApiTransport('xkeysib-uji'))->send($email);
    }
}
