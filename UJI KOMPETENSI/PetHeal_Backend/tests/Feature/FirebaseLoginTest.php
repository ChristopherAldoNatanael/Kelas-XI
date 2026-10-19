<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contract + regression tests untuk POST /api/auth/firebase-login.
 *
 * Regression utama (insiden 2026-10-18): token dengan `iat` beberapa detik
 * "di masa depan" (jam HP > jam server) ditolak oleh php-jwt karena leeway 0
 * → 401 "Invalid Firebase token". Test `iat` masa depan wajib tetap 200.
 */
class FirebaseLoginTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Keypair HANYA untuk test (bukan key produksi). Static karena
     * openssl_pkey_new gagal di mesin tanpa openssl.cnf valid.
     */
    private const PRIVATE_KEY_PEM = <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEpQIBAAKCAQEAuqlSznj8Z/D/YoDjEWZhUqdayhDHW83xA/vWYSHwcfMFqU1H
ejKkmFwS1mYCvP7vjMeMZPrnv3mNxRjl3IEwwu72POEqZhEpIxdc2PKy7ZTM1Xbr
kXC8EGqoUGp1e1NqNAs68DiWeKIO1F0WtWn+/LDs5TYQIHoGBuQ6rvQjXg56zO7g
8WwkROmFHyMubYv/rc0vAxywBwHIj2oAwc4ZLl9hkZaAKaZpJog7oY4m4JvClOYJ
/ssy/hNQT13GdRP0d6QHpsfGxZXrIdFpy/rsUIYvB0Q2uqaxXBfSNps1JQ98t5MB
XijCEeXruUC/OoPBA3B5FemsO3t9r7sLcUYgqQIDAQABAoIBAQCxtDGZT6U8bzsy
0/FYx1cwERNl+8NPqygg2silgGIgmXRyUZgSxdPdGQr2AffpZes442DNYfDvYRGO
6r0qx/NcIZNbMiHjOcMoHT3PbH7boCXBs41g0Sq5llDW/gMKfcdF9MsPl0/pWJ81
ncnYvCh8+RMOxAFmacUDGpSjqtlg2FX/28p3KDJMR5zAUxjNhGvK52r/q/i+U0Ow
mPtgsACv37MDWwq1Gjp3JYNs+TYOTnogdiVa3suMsTmF2RmLs6buOu0ymreuXByV
txxnBafaJDpS0dgcEVm86pUdAhn0EJm3TML6obZxkU1ZOTiG8P9QiKZ8fdZdd9tD
jxO4HrGBAoGBAPCISw4OIe38Qp2Pd9f+aRHZuL1Z3Ks/NIZwHkzpXX1Wey5o1uZu
xcNXeo2jbwPe88c9zefS4t3ayCpMx4xDlwp3S6julRV2faMKdMGTTzplvssLBqSi
vfcbzW7dMUQff00YLHipmc0zXWXSJ8sOhrgWbnhAvRKWNzCAe1kFgXN1AoGBAMaq
MTkf8GXLri/5CYo6oNbamUtTWhzU4eWS0c7adg1Rov+DaxW1V7zQM8+57PfYDHTc
l1791mJJSPTgE8jMCcl23h3eNZrJasIPeAY0yfbIwXa7bWrHJ39WfsScUFesEpMu
Mb4UrsrGRuDItSjSb7LizKrHLFNrgKnE4xn0FFXlAoGAK+YkFJ+dr6qfmdQKYmBr
Wreq0NwquxJhCrxBu3XH+00iVDkMJ6hF7b30fw85/rHdOhrcjpfdkfIRIC89KCtZ
0K4vH9jTWsRyb6RD5OR2zEivhq0vfDK7ivMHDlnOWQmKmU51J1yE1n+uZxvw6U1w
Zv6pEMd3+oFx9ycr2/Z2RtUCgYEAtuL+pYQNnui+1lMAwUvvvVtIO9vj3R7tRpa+
pxreCqzYzTiCYF2tA5j32Ydj61XtQbfBciVkMt8cVrHiBLwxVlIjcIOb/X7m1PfA
mpYGRzXRRCiPNflZFxTdZsov/EXgY7Xm/QG1V97oaQ7cF1SCl7/Kgn0RBUq2C5PB
LadRATECgYEAgSq5NaedMBdWX/wfnOBuZ2jhC+BGzVnuQoxCTz4WGjouFtYSfbbB
WNWYvup9SjKKO4wHv5RlaL4B5JNczJEWRuVqYzlzHQMvT7+USOZfItm3mPCOsEiG
X9ol50XOwhcTmt3bfU37U7F6LPXCNZDieu3Hw0PscLTHSWI5kRU8bJ4=
-----END RSA PRIVATE KEY-----
PEM;

    private const PUBLIC_KEY_PEM = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuqlSznj8Z/D/YoDjEWZh
UqdayhDHW83xA/vWYSHwcfMFqU1HejKkmFwS1mYCvP7vjMeMZPrnv3mNxRjl3IEw
wu72POEqZhEpIxdc2PKy7ZTM1XbrkXC8EGqoUGp1e1NqNAs68DiWeKIO1F0WtWn+
/LDs5TYQIHoGBuQ6rvQjXg56zO7g8WwkROmFHyMubYv/rc0vAxywBwHIj2oAwc4Z
Ll9hkZaAKaZpJog7oY4m4JvClOYJ/ssy/hNQT13GdRP0d6QHpsfGxZXrIdFpy/rs
UIYvB0Q2uqaxXBfSNps1JQ98t5MBXijCEeXruUC/OoPBA3B5FemsO3t9r7sLcUYg
qQIDAQAB
-----END PUBLIC KEY-----
PEM;

    private const KID = 'petheal-test-kid-0001';

    private string $kid;
    private string $privateKeyPem;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->privateKeyPem = self::PRIVATE_KEY_PEM;
        $this->publicKeyPem = self::PUBLIC_KEY_PEM;
        $this->kid = self::KID;
    }

    private function fakeGoogleKeys(string $kid, string $publicKeyPem, int $status = 200): void
    {
        Http::fake([
            'www.googleapis.com/robot/v1/metadata/x509/*' => Http::response(
                [$kid => $publicKeyPem],
                $status,
                ['Cache-Control' => 'public, max-age=3600']
            ),
        ]);
    }

    private function makeToken(array $overrides = []): string
    {
        $projectId = config('services.firebase.project_id');

        $payload = array_merge([
            'iss' => 'https://securetoken.google.com/' . $projectId,
            'aud' => $projectId,
            'sub' => 'uid-' . uniqid(),
            'email' => 'firebase.' . uniqid() . '@contract.test',
            'name' => 'Firebase User',
            'photo' => 'https://example.com/photo.jpg',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($payload, $this->privateKeyPem, 'RS256', $this->kid);
    }

    public function test_firebase_login_requires_id_token(): void
    {
        $res = $this->postJson('/api/auth/firebase-login', []);

        $res->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertArrayHasKey('errors', $res->json());
    }

    public function test_firebase_login_rejects_malformed_token(): void
    {
        $res = $this->postJson('/api/auth/firebase-login', ['id_token' => 'not-a-jwt-token']);

        $res->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'firebase_token_malformed',
            ]);
        $this->assertNotEmpty($res->json('message'));
    }

    public function test_firebase_login_accepts_token_with_future_iat_within_leeway(): void
    {
        $this->fakeGoogleKeys($this->kid, $this->publicKeyPem);

        // Root-cause regression: jam perangkat beberapa detik di depan server.
        $token = $this->makeToken(['iat' => time() + 30]);

        $res = $this->postJson('/api/auth/firebase-login', ['id_token' => $token]);

        $res->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Login successful']);
        $this->assertNotEmpty($res->json('data.token'));

        $user = User::where('email', $res->json('data.user.email'))->first();
        $this->assertNotNull($user, 'user harus terbuat dari klaim token');
        $this->assertNotNull($user->firebase_uid);
    }

    public function test_firebase_login_rejects_expired_token(): void
    {
        $this->fakeGoogleKeys($this->kid, $this->publicKeyPem);

        $token = $this->makeToken(['exp' => time() - 120]);

        $res = $this->postJson('/api/auth/firebase-login', ['id_token' => $token]);

        $res->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'firebase_token_expired',
            ]);
        $this->assertStringContainsString('kedaluwarsa', $res->json('message'));
    }

    public function test_firebase_login_rejects_token_from_other_firebase_project(): void
    {
        $this->fakeGoogleKeys($this->kid, $this->publicKeyPem);

        $token = $this->makeToken(['aud' => 'project-lain-bukan-petheal']);

        $res = $this->postJson('/api/auth/firebase-login', ['id_token' => $token]);

        $res->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'firebase_token_claims_mismatch',
            ]);
    }

    public function test_firebase_login_rejects_token_signed_by_unknown_key(): void
    {
        // Google hanya punya key lain — kid token tidak ada di mana pun.
        $this->fakeGoogleKeys('kid-lain-' . uniqid(), $this->publicKeyPem);

        $token = $this->makeToken();

        $res = $this->postJson('/api/auth/firebase-login', ['id_token' => $token]);

        $res->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'firebase_token_invalid_signature',
            ]);
    }

    public function test_firebase_login_returns_503_when_google_keys_unavailable(): void
    {
        $this->fakeGoogleKeys($this->kid, $this->publicKeyPem, 500);

        $token = $this->makeToken();

        $res = $this->postJson('/api/auth/firebase-login', ['id_token' => $token]);

        $res->assertStatus(503)
            ->assertJson([
                'success' => false,
                'code' => 'firebase_keys_unavailable',
            ]);
    }

    public function test_firebase_register_uses_same_error_codes(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'id_token' => 'garbage-token',
            'name' => 'Tester',
        ]);

        $res->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'firebase_token_malformed',
            ]);
    }

    public function test_firebase_login_success_envelope_and_clinic_binding(): void
    {
        $this->fakeGoogleKeys($this->kid, $this->publicKeyPem);

        $token = $this->makeToken();

        $res = $this->postJson('/api/auth/firebase-login', [
            'id_token' => $token,
            'clinic_slug' => null,
        ]);

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'user' => ['id', 'name', 'email', 'role', 'clinic']],
            ]);

        $user = User::where('email', $res->json('data.user.email'))->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->firebase_uid);
        $this->assertSame('user', $user->role);
    }
}
