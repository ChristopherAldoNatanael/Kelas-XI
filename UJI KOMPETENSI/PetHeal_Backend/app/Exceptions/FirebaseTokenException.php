<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Thrown when a Firebase ID token cannot be verified.
 *
 * Carries a stable machine-readable code (forwarded to API clients in the
 * `code` field) plus the HTTP status the API must answer with, so callers
 * can distinguish "user must retry with a fresh token" from "verification
 * infrastructure is down".
 */
class FirebaseTokenException extends Exception
{
    public const MALFORMED = 'firebase_token_malformed';
    public const EXPIRED = 'firebase_token_expired';
    public const NOT_YET_VALID = 'firebase_token_not_yet_valid';
    public const CLAIMS_MISMATCH = 'firebase_token_claims_mismatch';
    public const INVALID_SIGNATURE = 'firebase_token_invalid_signature';
    public const KEYS_UNAVAILABLE = 'firebase_keys_unavailable';

    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status = 401,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function malformed(string $reason = ''): self
    {
        if ($reason !== '') {
            Log::warning('Firebase token malformed: ' . $reason);
        }

        return new self(self::MALFORMED, 'Firebase token tidak valid atau rusak.');
    }

    public static function expired(): self
    {
        return new self(
            self::EXPIRED,
            'Sesi Google Anda sudah kedaluwarsa. Silakan coba masuk lagi.'
        );
    }

    public static function notYetValid(): self
    {
        return new self(
            self::NOT_YET_VALID,
            'Waktu token belum valid (perbedaan jam perangkat). Tunggu beberapa detik lalu coba lagi.'
        );
    }

    public static function claimsMismatch(string $detail): self
    {
        Log::warning('Firebase token claims mismatch: ' . $detail);

        return new self(
            self::CLAIMS_MISMATCH,
            'Token Firebase tidak cocok dengan proyek aplikasi ini.'
        );
    }

    public static function invalidSignature(string $detail = ''): self
    {
        if ($detail !== '') {
            Log::warning('Firebase token signature invalid: ' . $detail);
        }

        return new self(
            self::INVALID_SIGNATURE,
            'Firebase token tidak valid atau kedaluwarsa. Silakan coba masuk lagi.'
        );
    }

    public static function keysUnavailable(string $detail): self
    {
        Log::error('Firebase public keys unavailable: ' . $detail);

        return new self(
            self::KEYS_UNAVAILABLE,
            'Layanan verifikasi token sedang tidak tersedia. Coba lagi sebentar lagi.',
            503
        );
    }
}
