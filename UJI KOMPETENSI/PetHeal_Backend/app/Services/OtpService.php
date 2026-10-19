<?php

namespace App\Services;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Satu-satunya tempat kode OTP dibuat dan diperiksa — dipakai alur registrasi
 * email/sandi DAN reset kata sandi, supaya tidak ada dua mekanisme verifikasi
 * yang jalan sendiri-sendiri.
 *
 * Kode disimpan sebagai hash (bukan plaintext), punya masa berlaku, dan
 * maksimal 5 percobaan salah. Kode baru untuk email+tujuan yang sama menimpa
 * kode lama (updateOrInsert), jadi selalu hanya satu kode aktif.
 */
class OtpService
{
    public const PURPOSE_REGISTER = 'register';
    public const PURPOSE_RESET = 'reset';

    private const MAX_ATTEMPTS = 5;

    /** Masa berlaku kode per tujuan (menit). */
    private const TTL_MINUTES = [
        self::PURPOSE_REGISTER => 10,
        self::PURPOSE_RESET => 15,
    ];

    public function ttlMinutes(string $purpose): int
    {
        return self::TTL_MINUTES[$purpose] ?? self::TTL_MINUTES[self::PURPOSE_REGISTER];
    }

    public function maxAttempts(): int
    {
        return self::MAX_ATTEMPTS;
    }

    /**
     * Buat kode baru (menimpa kode lama untuk email+tujuan yang sama) dan
     * kembalikan kode plaintext-nya untuk dikirim lewat email.
     */
    public function issue(string $email, string $purpose, array $payload = []): string
    {
        $email = $this->normalize($email);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->prune();

        DB::table('otp_codes')->updateOrInsert(
            ['email' => $email, 'purpose' => $purpose],
            [
                'code_hash' => Hash::make($code),
                'payload' => $payload === [] ? null : json_encode($payload),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($this->ttlMinutes($purpose)),
                'consumed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $code;
    }

    /**
     * Kirim kode lewat email. Kegagalan TIDAK memblokir alur user, tetapi
     * selalu tercatat di log dan dilaporkan lewat nilai balik `false`, supaya
     * pemanggil bisa jujur bilang "email gagal dikirim" alih-alih mengklaim
     * kode sudah terkirim padahal tidak ada email yang keluar.
     */
    public function send(string $email, string $purpose, string $code): bool
    {
        try {
            Mail::to($email)->send(new OtpMail($code, $purpose, $this->ttlMinutes($purpose)));

            return true;
        } catch (\Exception $e) {
            Log::warning('OTP email gagal dikirim', [
                'email' => $email,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Payload pendaftaran yang tersimpan — dipakai saat kirim ulang kode. */
    public function payload(string $email, string $purpose): array
    {
        $record = DB::table('otp_codes')
            ->where('email', $this->normalize($email))
            ->where('purpose', $purpose)
            ->first();

        return $record ? $this->decodePayload($record->payload) : [];
    }

    /**
     * Periksa kode. Sengaja TIDAK menghapus baris apa pun saat gagal, supaya
     * kirim ulang masih bisa memakai payload pendaftaran yang sama.
     *
     * @return array{ok: bool, status: int, message: string, payload: array}
     */
    public function verify(string $email, string $purpose, string $code): array
    {
        $email = $this->normalize($email);

        $record = DB::table('otp_codes')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->first();

        if (!$record) {
            return $this->fail('Kode OTP tidak ditemukan. Silakan minta kode baru.');
        }

        if ($record->consumed_at !== null) {
            return $this->fail('Kode OTP telah digunakan. Silakan minta kode baru.');
        }

        if (now()->greaterThan(\Carbon\Carbon::parse($record->expires_at))) {
            return $this->fail('Kode OTP telah kedaluwarsa. Silakan minta kode baru.');
        }

        if ((int) $record->attempts >= self::MAX_ATTEMPTS) {
            return $this->fail('Terlalu banyak percobaan salah. Silakan minta kode baru.', 429);
        }

        if (!Hash::check($code, $record->code_hash)) {
            DB::table('otp_codes')->where('id', $record->id)->increment('attempts');
            $remaining = self::MAX_ATTEMPTS - ((int) $record->attempts + 1);

            return $this->fail(
                $remaining > 0
                    ? "Kode OTP yang Anda masukkan salah. Sisa percobaan: {$remaining}."
                    : 'Kode OTP yang Anda masukkan salah. Silakan minta kode baru.'
            );
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Kode OTP valid',
            'payload' => $this->decodePayload($record->payload),
        ];
    }

    /** Hapus kode setelah dipakai — satu kode hanya berlaku sekali. */
    public function consume(string $email, string $purpose): void
    {
        DB::table('otp_codes')
            ->where('email', $this->normalize($email))
            ->where('purpose', $purpose)
            ->delete();
    }

    public function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function fail(string $message, int $status = 400): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message, 'payload' => []];
    }

    private function decodePayload($raw): array
    {
        if (!$raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Housekeeping: buang kode yang kedaluwarsa lebih dari sehari. */
    private function prune(): void
    {
        DB::table('otp_codes')->where('expires_at', '<', now()->subDay())->delete();
    }
}
