<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan bahwa pendaftaran klinik baru disetujui.
 *
 * Dikirim setelah transaksi approve sukses — akun sudah ada, jadi email ini
 * hanya memberi tahu cara masuk, bukan membuat apa pun.
 */
class ClinicRegistrationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $clinicName,
        public string $ownerName,
        public string $loginUrl,
    ) {
    }

    public function build()
    {
        return $this
            ->subject("Klinik {$this->clinicName} disetujui — akses admin aktif")
            ->view('emails.clinic-registration-approved')
            ->text('emails.clinic-registration-approved-text')
            ->with([
                'clinicName' => $this->clinicName,
                'ownerName' => $this->ownerName,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
