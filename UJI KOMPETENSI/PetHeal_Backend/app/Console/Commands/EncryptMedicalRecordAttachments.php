<?php

namespace App\Console\Commands;

use App\Models\MedicalRecordAttachment;
use App\Services\MedicalRecordFileEncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Enkripsi berkas lampiran lama tanpa mengubah baris yang sudah terenkripsi.
 *
 * Tidak menghapus berkas dan tidak mengubah path. Hash konten asli tetap
 * dipertahankan/diisi dari plaintext, bukan dari ciphertext.
 */
class EncryptMedicalRecordAttachments extends Command
{
    protected $signature = 'medical-records:encrypt-attachments
        {--dry-run : Hitung tanpa menulis}
        {--chunk=100 : Jumlah baris per batch}';

    protected $description = 'Enkripsi at-rest lampiran rekam medis lama (AES-256-GCM)';

    public function handle(MedicalRecordFileEncryptionService $encryption): int
    {
        if (! $encryption->enabled()) {
            $this->error('Enkripsi lampiran dimatikan. Aktifkan MEDICAL_RECORDS_ATTACHMENT_ENCRYPTION_ENABLED.');

            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('medical_records.attachment_disk', MedicalRecordAttachment::DISK));

        $query = MedicalRecordAttachment::withTrashed()
            ->where(function ($q) {
                $q->whereNull('encryption_version')->orWhere('encryption_version', 0);
            });

        $total = (clone $query)->count();
        $this->info("Lampiran belum terenkripsi: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: tidak ada berkas yang diubah.');

            return self::SUCCESS;
        }

        $encrypted = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById((int) $this->option('chunk'), function ($attachments) use ($disk, $encryption, &$encrypted, &$skipped) {
            foreach ($attachments as $attachment) {
                try {
                    if (! $attachment->path || ! $disk->exists($attachment->path)) {
                        $skipped++;
                        $this->warn("Lampiran #{$attachment->id} dilewati: berkas tidak ditemukan.");
                        continue;
                    }

                    $contents = (string) $disk->get($attachment->path);

                    if ($encryption->isEncrypted($contents)) {
                        $attachment->forceFill(['encryption_version' => MedicalRecordFileEncryptionService::VERSION])->save();
                        $encrypted++;
                        continue;
                    }

                    $hash = hash('sha256', $contents);
                    $disk->put($attachment->path, $encryption->encrypt($contents));

                    $attachment->forceFill([
                        'encryption_version' => MedicalRecordFileEncryptionService::VERSION,
                        'sha256' => $attachment->sha256 ?: $hash,
                    ])->save();

                    $encrypted++;
                } catch (Throwable $e) {
                    $skipped++;
                    $this->warn("Lampiran #{$attachment->id} gagal: {$e->getMessage()}");
                }
            }
        });

        $this->info("Terenkripsi: {$encrypted}. Dilewati: {$skipped}.");

        return $skipped === 0 ? self::SUCCESS : self::FAILURE;
    }
}
