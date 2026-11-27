<?php

namespace App\Console\Commands;

use App\Services\MedicalRecordFileEncryptionService;
use Illuminate\Console\Command;

/**
 * Menampilkan sumber dan sidik jari kunci enkripsi lampiran.
 * Tidak pernah menampilkan material kunci.
 */
class ShowEncryptionKeyInfo extends Command
{
    protected $signature = 'medical-records:encryption-key-info {--json : Keluaran JSON}';

    protected $description = 'Tampilkan sumber dan sidik jari kunci enkripsi lampiran (tanpa kunci)';

    public function handle(MedicalRecordFileEncryptionService $encryption): int
    {
        $data = [
            'source' => $encryption->keySource(),
            'fingerprint' => $encryption->keyFingerprint(),
            'enabled' => $encryption->enabled(),
            'version' => MedicalRecordFileEncryptionService::VERSION,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Properti', 'Nilai'],
            [
                ['Sumber kunci', $data['source']],
                ['Sidik jari', $data['fingerprint']],
                ['Enkripsi aktif', $data['enabled'] ? 'ya' : 'tidak'],
                ['Versi format', (string) $data['version']],
            ]
        );

        $this->line('Material kunci tidak ditampilkan. Gunakan backup offline terpisah.');

        return self::SUCCESS;
    }
}
