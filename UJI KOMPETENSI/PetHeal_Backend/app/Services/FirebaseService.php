<?php

namespace App\Services;

use App\Exceptions\FirebaseTokenException;
use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

class FirebaseService
{
    private string $projectId;

    /** Toleransi selisih jam server vs jam perangkat (detik). */
    private int $leeway;

    public function __construct()
    {
        $this->projectId = (string) config('services.firebase.project_id', 'petheal-d8c3d');
        $this->leeway = max(0, (int) config('services.firebase.token_leeway', 60));

        // Akar bug 2026-10-18: leeway default php-jwt = 0, sehingga token
        // dengan `iat` beberapa detik "di masa depan" (jam HP > jam server)
        // dibuang sebagai BeforeValidException → 401 "Invalid Firebase token".
        JWT::$leeway = $this->leeway;
    }

    /**
     * Verify a Firebase ID Token.
     *
     * @return array{uid: string, email: ?string, name: ?string, picture: ?string, email_verified: bool}
     *
     * @throws FirebaseTokenException bila token tidak bisa dipercaya
     *                                (status code & message sudah final)
     */
    public function verifyIdToken(string $idToken): array
    {
        if (trim($idToken) === '') {
            throw FirebaseTokenException::malformed('empty id_token');
        }

        // Jalur Admin SDK hanya dipakai bila SDK-nya terpasang; jika tidak,
        // langsung jatuh ke verifikasi manual (deterministik, tanpa error log
        // "Class not found" di tiap request).
        $kreaitResult = $this->verifyWithKreait($idToken);
        if ($kreaitResult !== null) {
            return $kreaitResult;
        }

        return $this->verifyFirebaseTokenManually($idToken);
    }

    /**
     * Try verification using kreait SDK (opsional — hanya bila terpasang).
     */
    private function verifyWithKreait(string $idToken): ?array
    {
        if (!class_exists(\Kreait\Firebase\Factory::class)) {
            return null;
        }

        try {
            $configured = (string) (config('services.firebase.credentials') ?? '');
            $credentialsPath = ($configured !== '' && file_exists($configured))
                ? $configured
                : storage_path('app/firebase-service-account.json');

            if (!file_exists($credentialsPath)) {
                Log::info('Firebase credentials file not found at: ' . $credentialsPath);
                return null;
            }

            $factory = (new \Kreait\Firebase\Factory())->withServiceAccount($credentialsPath);
            $auth = $factory->createAuth();

            $verifiedIdToken = $auth->verifyIdToken($idToken, true);
            $claims = $verifiedIdToken->claims()->all();

            $this->assertClaims($claims);
            Log::info('Kreait Firebase token verified, uid: ' . $claims['sub']);

            return $this->mapClaims($claims);
        } catch (FirebaseTokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Fallback ke verifikasi manual (leeway & pesan error lebih jelas).
            Log::warning('Kreait verification failed, falling back to manual: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Manual verification: base64url decode header → resolve public key per
     * `kid` → JWT::decode (RS256 + leeway) → validasi claims Firebase.
     *
     * @throws FirebaseTokenException
     */
    private function verifyFirebaseTokenManually(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw FirebaseTokenException::malformed('token does not have 3 segments');
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        if (!is_array($header)) {
            throw FirebaseTokenException::malformed('header is not valid JSON');
        }

        $alg = $header['alg'] ?? 'none';
        if ($alg !== 'RS256') {
            throw FirebaseTokenException::malformed('unsupported alg: ' . $alg);
        }

        $kid = $header['kid'] ?? '';
        if (!is_string($kid) || $kid === '') {
            throw FirebaseTokenException::malformed('missing kid in header');
        }

        $publicKey = $this->resolvePublicKey($kid);

        try {
            $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));
        } catch (ExpiredException) {
            throw FirebaseTokenException::expired();
        } catch (BeforeValidException $e) {
            // iat/nbf masih "di masa depan" melebihi leeway → clock skew parah.
            Log::warning(
                'Firebase token timestamp beyond leeway (' . $this->leeway . 's): ' . $e->getMessage()
            );
            throw FirebaseTokenException::notYetValid();
        } catch (SignatureInvalidException) {
            throw FirebaseTokenException::invalidSignature('kid ' . $kid . ' did not verify');
        } catch (UnexpectedValueException | InvalidArgumentException | DomainException $e) {
            throw FirebaseTokenException::malformed($e->getMessage());
        }

        $claims = (array) $decoded;
        $this->assertClaims($claims);

        Log::info('Firebase token verified for uid: ' . $claims['sub']);

        return $this->mapClaims($claims);
    }

    /**
     * Ambil public key untuk `kid`; refresh cache sekali bila kid belum
     * terdaftar (Google melakukan rotasi sertifikat).
     *
     * @throws FirebaseTokenException
     */
    private function resolvePublicKey(string $kid): string
    {
        $publicKeys = $this->getPublicKeys();
        if (isset($publicKeys[$kid])) {
            return $publicKeys[$kid];
        }

        Log::warning('Firebase kid ' . $kid . ' not in cached keys, refreshing...');
        Cache::forget('firebase_public_keys');

        $publicKeys = $this->getPublicKeys();
        if (isset($publicKeys[$kid])) {
            return $publicKeys[$kid];
        }

        throw FirebaseTokenException::invalidSignature(
            'kid ' . $kid . ' unknown (token from another project or forged)'
        );
    }

    /**
     * Validasi claims wajib Firebase ID token (issuer, audience, sub, exp).
     *
     * @throws FirebaseTokenException
     */
    private function assertClaims(array $claims): void
    {
        $expectedIssuer = 'https://securetoken.google.com/' . $this->projectId;
        $issuer = (string) ($claims['iss'] ?? '');
        if ($issuer !== $expectedIssuer) {
            throw FirebaseTokenException::claimsMismatch('iss=' . $issuer . ' expected=' . $expectedIssuer);
        }

        $audience = (string) ($claims['aud'] ?? '');
        if ($audience !== $this->projectId) {
            throw FirebaseTokenException::claimsMismatch('aud=' . $audience . ' expected=' . $this->projectId);
        }

        if (empty($claims['sub']) || !is_string($claims['sub'])) {
            throw FirebaseTokenException::malformed('missing sub claim');
        }

        // JWT::decode sudah cek exp dengan leeway; cek ulang dengan toleransi
        // yang sama supaya kedua jalur tidak saling bertentangan.
        if (isset($claims['exp']) && (int) $claims['exp'] < time() - $this->leeway) {
            throw FirebaseTokenException::expired();
        }
    }

    /**
     * @return array{uid: string, email: ?string, name: ?string, picture: ?string, email_verified: bool}
     */
    private function mapClaims(array $claims): array
    {
        return [
            'uid' => (string) $claims['sub'],
            'email' => $claims['email'] ?? null,
            'name' => $claims['name'] ?? null,
            'picture' => $claims['picture'] ?? null,
            'email_verified' => (bool) ($claims['email_verified'] ?? false),
        ];
    }

    private function base64UrlDecode(string $input): string
    {
        $base64 = strtr($input, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * Get Firebase public keys, caching with TTL from Google's Cache-Control header.
     *
     * @throws FirebaseTokenException bila Google tidak bisa diakses
     */
    private function getPublicKeys(): array
    {
        return Cache::remember('firebase_public_keys', 3600, function () {
            Log::info('Fetching Firebase public keys from Google...');

            try {
                $response = Http::get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
            } catch (Throwable $e) {
                throw FirebaseTokenException::keysUnavailable($e->getMessage());
            }

            if (!$response->successful()) {
                throw FirebaseTokenException::keysUnavailable('HTTP ' . $response->status());
            }

            $keys = $response->json();
            if (!is_array($keys) || $keys === []) {
                throw FirebaseTokenException::keysUnavailable('empty key set from Google');
            }

            Log::info('Firebase public keys fetched, count: ' . count($keys));

            // Pakai Cache-Control max-age milik Google bila tersedia.
            $cacheControl = $response->header('Cache-Control');
            if ($cacheControl && preg_match('/max-age=(\d+)/', $cacheControl, $matches)) {
                Cache::put('firebase_public_keys', $keys, (int) $matches[1]);
            }

            return $keys;
        });
    }
}
