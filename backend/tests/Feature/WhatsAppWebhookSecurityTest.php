<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The webhook is the only unauthenticated route in the WhatsApp surface, so it
 * carries the security burden on its own. These tests pin that behaviour down:
 * unsigned or mis-signed payloads must never reach the message store.
 */
class WhatsAppWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';
    private const CACHE_KEY = 'whatsapp:inbound:messages';

    private function payload(): array
    {
        return [
            'event' => 'message',
            'data' => [
                'from' => '971501234567@c.us',
                'body' => 'Hello from a tenant',
                'timestamp' => '2026-01-01T10:00:00.000Z',
            ],
        ];
    }

    private function deliver(array $payload, ?string $signature)
    {
        $body = json_encode($payload);

        return $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            $signature ? ['HTTP_X-OpenWA-Signature' => $signature] : [],
            $body
        );
    }

    /** @test */
    public function it_rejects_unsigned_payloads_when_a_secret_is_configured(): void
    {
        config(['services.openwa.webhook_secret' => self::SECRET]);

        $response = $this->deliver($this->payload(), null);

        $response->assertStatus(401);
        $this->assertSame([], Cache::get(self::CACHE_KEY, []));
    }

    /** @test */
    public function it_rejects_a_signature_that_does_not_match(): void
    {
        config(['services.openwa.webhook_secret' => self::SECRET]);

        $response = $this->deliver($this->payload(), 'not-the-right-signature');

        $response->assertStatus(401);
        $this->assertSame([], Cache::get(self::CACHE_KEY, []));
    }

    /** @test */
    public function it_accepts_a_correctly_signed_payload(): void
    {
        config(['services.openwa.webhook_secret' => self::SECRET]);

        $payload = $this->payload();
        $signature = hash_hmac('sha256', json_encode($payload), self::SECRET);

        $response = $this->deliver($payload, $signature);

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $stored = Cache::get(self::CACHE_KEY, []);
        $this->assertCount(1, $stored);
        $this->assertSame('Hello from a tenant', $stored[0]['body']);
        $this->assertSame('inbound', $stored[0]['direction']);
    }

    /** @test */
    public function it_refuses_to_trust_callers_while_no_secret_is_configured(): void
    {
        config(['services.openwa.webhook_secret' => '']);

        $response = $this->deliver($this->payload(), 'anything-at-all');

        // Silently accepting here would let anyone inject fake inbound
        // messages into every manager's inbox.
        $response->assertStatus(503);
        $this->assertSame([], Cache::get(self::CACHE_KEY, []));
    }

    /** @test */
    public function it_records_a_session_status_event(): void
    {
        config(['services.openwa.webhook_secret' => self::SECRET]);

        $payload = ['event' => 'session.status', 'data' => ['status' => 'ready']];
        $signature = hash_hmac('sha256', json_encode($payload), self::SECRET);

        $this->deliver($payload, $signature)->assertStatus(200);

        $this->assertSame('ready', Cache::get('whatsapp:session_status'));
    }
}