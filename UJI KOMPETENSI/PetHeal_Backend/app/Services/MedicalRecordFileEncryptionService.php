<?php

namespace App\Services;

use RuntimeException;

/**
 * Enkripsi at-rest lampiran rekam medis.
 *
 * Format berkas:
 *   MAGIC (6 byte "PHRM1\0") | IV (12 byte) | TAG (16 byte) | ciphertext
 *
 * AES-256-GCM dipakai karena menyediakan confidentiality + integrity. Header
 * MAGIC dijadikan additional authenticated data sehingga versi format tidak
 * dapat diubah tanpa membuat autentikasi gagal.
 *
 * Berkas lama tanpa header tetap dibaca apa adanya. Ini penting agar migrasi
 * tidak memutus akses arsip.
 */
class MedicalRecordFileEncryptionService
{
    public const VERSION = 1;
    public const MAGIC = "PHRM1\0";
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private ?string $cachedKey = null;

    public function enabled(): bool
    {
        return (bool) config('medical_records.attachment_encryption_enabled', true);
    }

    public function isEncrypted(string $contents): bool
    {
        return str_starts_with($contents, self::MAGIC);
    }

    public function encrypt(string $plaintext): string
    {
        if (! $this->enabled()) {
            return $plaintext;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::MAGIC,
            self::TAG_LENGTH
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Gagal mengenkripsi lampiran rekam medis.');
        }

        return self::MAGIC . $iv . $tag . $ciphertext;
    }

    public function decrypt(string $contents): string
    {
        if (! $this->isEncrypted($contents)) {
            return $contents;
        }

        $minimum = strlen(self::MAGIC) + self::IV_LENGTH + self::TAG_LENGTH;

        if (strlen($contents) < $minimum) {
            throw new RuntimeException('Berkas lampiran terenkripsi rusak.');
        }

        $iv = substr($contents, strlen(self::MAGIC), self::IV_LENGTH);
        $tag = substr($contents, strlen(self::MAGIC) + self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($contents, $minimum);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::MAGIC
        );

        if ($plaintext === false) {
            throw new RuntimeException('Autentikasi berkas lampiran gagal; berkas mungkin berubah.');
        }

        return $plaintext;
    }

    /** Sidik jari kunci untuk verifikasi backup; bukan kunci itu sendiri. */
    public function keyFingerprint(): string
    {
        return substr(hash('sha256', $this->key()), 0, 16);
    }

    /** Sumber kunci, tanpa membocorkan nilainya. */
    public function keySource(): string
    {
        return (string) config('medical_records.attachment_encryption_key', '') !== ''
            ? 'MEDICAL_RECORDS_ATTACHMENT_KEY'
            : 'APP_KEY';
    }

    /**
     * Material kunci untuk backup offline. Pemanggil wajib menjaga kerahasiaan
     * dan tidak boleh menuliskan hasilnya ke log/response.
     */
    public function exportKeyMaterial(): string
    {
        return base64_encode($this->key());
    }

    private function key(): string
    {
        if ($this->cachedKey !== null) {
            return $this->cachedKey;
        }

        $configured = (string) config('medical_records.attachment_encryption_key', '');

        if ($configured !== '') {
            return $this->cachedKey = hash('sha256', $configured . '|petheal-medical-records', true);
        }

        $appKey = (string) config('app.key', '');

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('APP_KEY tidak valid untuk enkripsi lampiran.');
            }

            $appKey = $decoded;
        }

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY kosong; enkripsi lampiran tidak dapat dijalankan.');
        }

        return $this->cachedKey = hash('sha256', $appKey . '|petheal-medical-records-attachments-v1', true);
    }
}
