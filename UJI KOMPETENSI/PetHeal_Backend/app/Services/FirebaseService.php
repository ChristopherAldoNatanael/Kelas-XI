<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseService
{
    private string $projectId;

    public function __construct()
    {
        $this->projectId = env('FIREBASE_PROJECT_ID', 'petheal-d8c3d');
    }

    /**
     * Verify Firebase ID Token
     *
     * @param string $idToken
     * @return array|null Returns decoded token data or null if invalid
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            Log::info('Starting Firebase ID token verification for project: ' . $this->projectId);

            // Try to verify using kreait SDK (if credentials exist)
            $kreaitResult = $this->verifyWithKreait($idToken);
            if ($kreaitResult) {
                return $kreaitResult;
            }

            // Fallback to manual verification
            Log::info('Trying manual Firebase token verification');
            return $this->verifyFirebaseTokenManually($idToken);
        } catch (\Exception $e) {
            Log::error('Firebase token verification exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Try verification using kreait SDK
     */
    private function verifyWithKreait(string $idToken): ?array
    {
        try {
            // Check if credentials file exists
            $credentialsPath = storage_path('app/firebase-service-account.json');
            if (!file_exists($credentialsPath)) {
                Log::info('Firebase credentials file not found at: ' . $credentialsPath);
                return null;
            }

            $factory = (new \Kreait\Firebase\Factory())->withServiceAccount($credentialsPath);
            $auth = $factory->createAuth();

            $verifiedIdToken = $auth->verifyIdToken($idToken, true);
            $claims = $verifiedIdToken->claims()->all();

            Log::info('Kreait Firebase token verified successfully, uid: ' . ($claims['sub'] ?? 'unknown'));

            return [
                'uid' => $claims['sub'] ?? null,
                'email' => $claims['email'] ?? null,
                'name' => $claims['name'] ?? null,
                'picture' => $claims['picture'] ?? null,
                'email_verified' => $claims['email_verified'] ?? false,
            ];
        } catch (Throwable $e) {
            Log::warning('Kreait verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Manual Firebase token verification
     */
    private function verifyFirebaseTokenManually(string $idToken): ?array
    {
        try {
            // Decode the token to get header
            $tokenParts = explode('.', $idToken);
            if (count($tokenParts) !== 3) {
                Log::warning('Firebase token has wrong number of parts');
                return null;
            }

            $header = json_decode(base64_decode($tokenParts[0]), true);
            Log::info('Token header: ' . json_encode($header));

            if (!isset($header['kid'])) {
                Log::warning('Token has no kid in header');
                return null;
            }

            $kid = $header['kid'];
            Log::info('Looking for key with kid: ' . $kid);

            // Get public keys (cached with TTL from Google headers)
            $publicKeys = $this->getPublicKeys();

            Log::info('Available public keys: ' . implode(', ', array_keys($publicKeys)));

            if (!isset($publicKeys[$kid])) {
                Log::warning('Key not found for kid: ' . $kid . ', trying all available keys...');

                // Try with each available key
                foreach ($publicKeys as $keyKid => $publicKey) {
                    try {
                        $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));
                        $decodedArray = (array) $decoded;

                        // Verify issuer and audience
                        if (!$this->validateClaims($decodedArray)) {
                            continue;
                        }

                        Log::info('Token verified successfully with kid: ' . $keyKid);

                        return [
                            'uid' => $decodedArray['sub'] ?? null,
                            'email' => $decodedArray['email'] ?? null,
                            'name' => $decodedArray['name'] ?? null,
                            'picture' => $decodedArray['picture'] ?? null,
                            'email_verified' => $decodedArray['email_verified'] ?? false,
                        ];
                    } catch (\Exception $e) {
                        // Try next key
                        continue;
                    }
                }

                return null;
            }

            // Verify with matching key
            $decoded = JWT::decode($idToken, new Key($publicKeys[$kid], 'RS256'));
            $decodedArray = (array) $decoded;

            // Validate claims
            if (!$this->validateClaims($decodedArray)) {
                return null;
            }

            Log::info('Firebase token verified successfully for uid: ' . ($decodedArray['sub'] ?? 'unknown'));

            return [
                'uid' => $decodedArray['sub'] ?? null,
                'email' => $decodedArray['email'] ?? null,
                'name' => $decodedArray['name'] ?? null,
                'picture' => $decodedArray['picture'] ?? null,
                'email_verified' => $decodedArray['email_verified'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Manual Firebase token verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate token claims (issuer, audience, expiration)
     */
    private function validateClaims(array $decodedArray): bool
    {
        // Verify issuer
        $expectedIssuer = 'https://securetoken.google.com/' . $this->projectId;
        if (($decodedArray['iss'] ?? '') !== $expectedIssuer) {
            Log::warning('Issuer mismatch: expected ' . $expectedIssuer . ', got ' . ($decodedArray['iss'] ?? 'none'));
            return false;
        }

        // Verify audience
        if (($decodedArray['aud'] ?? '') !== $this->projectId) {
            Log::warning('Audience mismatch: expected ' . $this->projectId . ', got ' . ($decodedArray['aud'] ?? 'none'));
            return false;
        }

        // Check expiration
        if (($decodedArray['exp'] ?? 0) < time()) {
            Log::warning('Token has expired');
            return false;
        }

        return true;
    }

    /**
     * Get Firebase public keys, caching with TTL from Google's Cache-Control header
     */
    private function getPublicKeys(): array
    {
        return Cache::remember('firebase_public_keys', 3600, function () {
            Log::info('Fetching Firebase public keys from Google...');

            $response = Http::get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');

            if ($response->successful()) {
                $keys = $response->json();
                Log::info('Firebase public keys fetched, count: ' . count($keys));

                // Try to use the Cache-Control max-age from Google's response
                $cacheControl = $response->header('Cache-Control');
                if ($cacheControl && preg_match('/max-age=(\d+)/', $cacheControl, $matches)) {
                    $ttl = (int) $matches[1];
                    Cache::put('firebase_public_keys', $keys, $ttl);
                }

                return $keys;
            }

            Log::error('Failed to fetch Firebase public keys: ' . $response->status());
            throw new \Exception('Failed to fetch Firebase public keys');
        });
    }
}
