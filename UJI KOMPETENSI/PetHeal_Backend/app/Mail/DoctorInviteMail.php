<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Undangan aktivasi akun portal dokter.
 *
 * Berbeda dari OTP admin: tidak ada kode yang harus diketik. Tautan di email
 * sudah membawa token acak (disimpan sebagai hash di database), berlaku 60
 * menit, dan hangus setelah dipakai. Dokter tinggal membuat kata sandi.
 */
class DoctorInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $activationUrl,
        public string $clinicName,
        public ?string $recipientName = null,
        public int $validMinutes = 60,
    ) {
    }

    public function build()
    {
        $name = trim((string) $this->recipientName);
        $greeting = $name !== '' ? 'Halo ' . $name . ',' : 'Halo,';

        return $this
            ->subject("Aktivasi akun dokter — {$this->clinicName}")
            ->view('emails.doctor-invite')
            ->text('emails.doctor-invite-text')
            ->with([
                'greeting' => $greeting,
                'validMinutes' => $this->validMinutes,
            ]);
    }
}
