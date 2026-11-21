<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email uji pengiriman — dipakai `php artisan petheal:mail-test`.
 *
 * Sengaja TIDAK memakai template OTP dan tidak berisi kode apa pun: tujuannya
 * hanya membuktikan jalur SMTP benar-benar mengantar email ke kotak masuk
 * penerima. Karena itu email ini aman dikirim berkali-kali dan tidak bisa
 * dipakai untuk masuk ke akun siapa pun.
 */
class MailDeliveryCheck extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $sentAt,
        public string $mailerName,
        public string $fromAddress,
    ) {
    }

    public function build()
    {
        return $this
            ->subject('Uji pengiriman email PetHeal')
            ->view('emails.mail-check')
            ->text('emails.mail-check-text')
            ->with([
                'sentAt' => $this->sentAt,
                'mailerName' => $this->mailerName,
                'fromAddress' => $this->fromAddress,
                'appUrl' => config('app.url'),
            ]);
    }
}
