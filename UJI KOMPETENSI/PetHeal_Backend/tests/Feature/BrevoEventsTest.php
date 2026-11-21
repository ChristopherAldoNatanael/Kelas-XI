<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Perintah `petheal:brevo-events` — pembaca log pengiriman Brevo.
 *
 * Tanpa alat ini, "email tidak sampai" hanya bisa ditebak. Dengan alat ini
 * statusnya terbaca: delivered, blocked, bounced, atau deferred + alasannya.
 */
class BrevoEventsTest extends TestCase
{
    public function test_it_reminds_to_fill_the_api_key(): void
    {
        Config::set('services.brevo.key', null);

        $this->artisan('petheal:brevo-events')
            ->expectsOutputToContain('BREVO_API_KEY')
            ->assertFailed();
    }

    public function test_it_lists_delivery_events(): void
    {
        Config::set('services.brevo.key', 'xkeysib-uji');

        Http::fake([
            'api.brevo.com/v3/smtp/statistics/events*' => Http::response([
                'events' => [
                    [
                        'date' => '2026-11-21 22:16:10',
                        'event' => 'delivered',
                        'email' => 'pemilik@contoh.test',
                        'reason' => '',
                        'message-id' => '<2026.petheal@smtp-relay.brevo.com>',
                    ],
                    [
                        'date' => '2026-11-21 22:15:02',
                        'event' => 'blocked',
                        'email' => 'pemilik@contoh.test',
                        'reason' => 'sender not authenticated',
                        'message-id' => '<2026.lama@smtp-relay.brevo.com>',
                    ],
                    [
                        'date' => '2026-11-21 22:14:00',
                        'event' => 'delivered',
                        'email' => 'orang.lain@contoh.test',
                        'reason' => '',
                        'message-id' => '<2026.lain@smtp-relay.brevo.com>',
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('petheal:brevo-events', ['email' => 'pemilik@contoh.test'])
            ->expectsOutputToContain('delivered')
            ->expectsOutputToContain('sender not authenticated')
            ->assertSuccessful();

        // Saringan email dilakukan lokal, jadi permintaan tidak memakai parameter email.
        Http::assertSent(fn ($request) => !str_contains($request->url(), 'email='));
    }

    public function test_it_explains_an_invalid_api_key(): void
    {
        Config::set('services.brevo.key', 'xkeysib-salah');

        Http::fake([
            'api.brevo.com/*' => Http::response(['message' => 'Key not found'], 401),
        ]);

        $this->artisan('petheal:brevo-events')
            ->expectsOutputToContain('401')
            ->assertFailed();
    }
}
