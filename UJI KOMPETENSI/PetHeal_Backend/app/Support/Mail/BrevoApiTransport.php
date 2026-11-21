<?php

namespace App\Support\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Transport Brevo lewat REST API (bukan SMTP relay).
 *
 * Dipakai karena SMTP relay menolak autentikasi dengan 535 ketika login/key
 * tidak cocok — kegagalan yang tidak terlihat dari sisi aplikasi. API hanya
 * butuh satu kunci (api-key) dan mengembalikan Message-ID serta pesan error
 * yang jelas, sehingga penyebab kegagalan langsung terbaca.
 *
 * Terdaftar sebagai transport `brevo-api` (lihat AppServiceProvider) dan mailer
 * `brevo-api` di config/mail.php. Hanya dipakai email verifikasi.
 */
final class BrevoApiTransport implements TransportInterface
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);

        $email = $message instanceof Email ? $message : $message->getOriginalMessage();

        if (!$email instanceof Email) {
            throw new TransportException('Brevo API hanya bisa mengirim pesan Email.');
        }

        $payload = [
            'sender' => $this->toAddress($email->getFrom()[0] ?? $envelope->getSender()),
            'to' => $this->toAddresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
            'htmlContent' => (string) $email->getHtmlBody(),
            'textContent' => (string) $email->getTextBody(),
        ];

        if ($email->getCc() !== []) {
            $payload['cc'] = $this->toAddresses($email->getCc());
        }

        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $this->toAddress($email->getReplyTo()[0]);
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])->timeout(20)->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            // Pesan Brevo diteruskan apa adanya: "Sender not valid",
            // "account under validation", atau 401 bila kunci salah.
            $reason = $response->json('message') ?? $response->body();

            throw new TransportException(
                'Brevo API menolak pengiriman: ' . mb_substr((string) $reason, 0, 250),
                $response->status(),
            );
        }

        $sent = new SentMessage($message, $envelope);
        $sent->setMessageId((string) ($response->json('messageId') ?? ''));

        return $sent;
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }

    /**
     * Brevo menolak permintaan yang memuat "name" kosong ("name is missing in
     * to"), jadi kunci name hanya dikirim bila benar-benar ada isinya.
     *
     * @return array{email: string, name?: string}
     */
    private function toAddress(Address $address): array
    {
        $payload = ['email' => $address->getAddress()];
        $name = trim((string) $address->getName());

        if ($name !== '') {
            $payload['name'] = $name;
        }

        return $payload;
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function toAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->toAddress($address), $addresses);
    }
}
