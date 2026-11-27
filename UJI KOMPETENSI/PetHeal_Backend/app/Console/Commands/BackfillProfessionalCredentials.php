<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\ProfessionalCredential;
use App\Services\ProfessionalCredentialService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill STR/SIP legacy dari tabel doctors ke professional_credentials.
 * Tidak mengarang masa berlaku: nomor tanpa tanggal berlaku menjadi `pending`
 * (exception) sampai diverifikasi manual.
 */
class BackfillProfessionalCredentials extends Command
{
    protected $signature = 'credentials:backfill-from-doctors
        {--dry-run : Hitung tanpa menulis}
        {--chunk=200 : Jumlah dokter per batch}';

    protected $description = 'Backfill STR/SIP dokter lama ke tabel kredensial';

    public function handle(ProfessionalCredentialService $credentials): int
    {
        $query = Doctor::query()
            ->where(function ($q) {
                $q->whereNotNull('str_number')->where('str_number', '!=', '')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('sip_number')->where('sip_number', '!=', '');
                    });
            })
            ->orderBy('id');

        $total = (clone $query)->count();
        $this->info("Dokter dengan data STR/SIP legacy: {$total}");

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $failed = [];

        $query->chunkById(max(20, (int) $this->option('chunk')), function ($doctors) use ($credentials, &$created, &$skipped, &$failed) {
            foreach ($doctors as $doctor) {
                foreach ([
                    ProfessionalCredential::TYPE_STR => 'str_number',
                    ProfessionalCredential::TYPE_SIP => 'sip_number',
                ] as $type => $numberField) {
                    $number = trim((string) $doctor->{$numberField});

                    if ($number === '') {
                        continue;
                    }

                    $existing = ProfessionalCredential::where('doctor_id', $doctor->id)
                        ->where('credential_type', $type)
                        ->where('is_current', true)
                        ->exists();

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    $expiry = $type === ProfessionalCredential::TYPE_STR
                        ? $doctor->str_expiry
                        : $doctor->sip_expiry;

                    $status = $expiry && $expiry->gte(today())
                        ? ProfessionalCredential::STATUS_ACTIVE
                        : ($expiry ? ProfessionalCredential::STATUS_EXPIRED : ProfessionalCredential::STATUS_PENDING);

                    try {
                        $credentials->issue([
                            'clinic_id' => $doctor->clinic_id,
                            'doctor_id' => $doctor->id,
                            'credential_type' => $type,
                            'number' => $number,
                            'expires_at' => $expiry?->toDateString(),
                            'status' => $status,
                            'verified_at' => null,
                            'notes' => 'Backfill dari kolom legacy doctors; perlu verifikasi dokumen.',
                        ]);
                        $created++;
                    } catch (Throwable $e) {
                        $failed[] = [
                            'doctor_id' => $doctor->id,
                            'type' => $type,
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }
        });

        $this->info("Kredensial dibuat: {$created}; dilewati (sudah ada): {$skipped}");

        if ($failed !== []) {
            $this->error('Gagal: ' . count($failed));
            $this->table(['doctor_id', 'type', 'message'], array_slice($failed, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
