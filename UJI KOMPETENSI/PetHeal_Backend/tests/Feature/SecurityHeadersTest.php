<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_pages_send_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }
}
