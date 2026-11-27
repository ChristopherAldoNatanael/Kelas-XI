<?php

namespace App\Console\Commands;

use App\Services\MedicalRecordFileEncryptionService;
use Illuminate\Console\Command;

/**
 * Backup material kunci enkripsi lampiran ke berkas offline.
 *
 * Sengaja tidak memiliki default path dan menolak menulis di dalam folder
 * proyek agar tidak ikut ter-commit. Berkas ditulis dengan permission 0600.
 */
class BackupEncryptionKey extends Command
{
    protected $signature = 'medical-records:backup-encryption-key
        {--output= : Path absolut di luar folder proyek}
        {--confirm= : Tulis BACKUP untuk konfirmasi}
        {--force : Timpa berkas yang sudah ada}';

    protected $description = 'Backup material kunci enkripsi lampiran ke berkas offline 0600';

    public function handle(MedicalRecordFileEncryptionService $encryption): int
    {
        if ($this->option('confirm') !== 'BACKUP') {
            $this->error('Tambahkan --confirm=BACKUP untuk benar-benar menulis material kunci.');

            return self::FAILURE;
        }

        $output = trim((string) $this->option('output'));

        if ($output === '') {
            $this->error('--output wajib diisi dan harus berada di luar folder proyek.');

            return self::FAILURE;
        }

        $directory = dirname($output);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Folder tujuan tidak dapat dibuat.');

            return self::FAILURE;
        }

        $realDirectory = realpath($directory);

        if ($realDirectory === false) {
            $this->error('Folder tujuan tidak valid.');

            return self::FAILURE;
        }

        $fullPath = rtrim($realDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($output);
        $projectRoot = rtrim((string) realpath(base_path()), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (str_starts_with(strtolower($fullPath), strtolower($projectRoot))) {
            $this->error('Backup tidak boleh ditulis di dalam folder proyek. Gunakan media offline.');

            return self::FAILURE;
        }

        if (is_file($fullPath) && ! $this->option('force')) {
            $this->error('Berkas sudah ada. Gunakan --force hanya bila yakin ingin menimpa.');

            return self::FAILURE;
        }

        $payload = json_encode([
            'type' => 'petheal-medical-record-attachment-key',
            'version' => MedicalRecordFileEncryptionService::VERSION,
            'source' => $encryption->keySource(),
            'fingerprint' => $encryption->keyFingerprint(),
            'key_material_base64' => $encryption->exportKeyMaterial(),
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($payload === false || @file_put_contents($fullPath, $payload, LOCK_EX) === false) {
            $this->error('Backup kunci gagal ditulis.');

            return self::FAILURE;
        }

        @chmod($fullPath, 0600);

        $this->info('Backup kunci ditulis.');
        $this->line('Path: ' . $fullPath);
        $this->line('Sidik jari: ' . $encryption->keyFingerprint());
        $this->warn('Simpan berkas ini di media terenkripsi/offline. Jangan commit atau unggah.');

        return self::SUCCESS;
    }
}
