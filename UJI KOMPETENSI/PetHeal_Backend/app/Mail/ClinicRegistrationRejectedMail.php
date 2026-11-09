<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan bahwa pendaftaran klinik baru ditolak. Tidak ada akun/klinik
 * yang dibuat; alasan opsional diambil dari review_note super admin.
 */
class ClinicRegistrationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $clinicName,
        public string $ownerName,
        public ?string $reason = null,
    ) {
    }

    public function build()
    {
        return $this
            ->subject("Pendaftaran klinik {$this->clinicName} belum disetujui")
            ->view('emails.clinic-registration-rejected')
            ->text('emails.clinic-registration-rejected-text')
            ->with([
                'clinicName' => $this->clinicName,
                'ownerName' => $this->ownerName,
                'reason' => $this->reason,
            ]);
    }
}
