<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the WhatsApp integration via OpenWA.
 *
 * These tests mock the OpenWA HTTP API so they run without a live
 * OpenWA container. They verify that the WhatsAppService correctly
 * formats requests and handles responses.
 */
class WhatsAppTest extends TestCase
{
    // The route-level cases resolve a bearer token through the sanctum guard,
    // which touches personal_access_tokens.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openwa.base_url' => 'http://openwa:2785',
            'services.openwa.api_key' => 'test-api-key',
            'services.openwa.session_id' => 'test-session-uuid',
        ]);
    }

    /** @test */
    public function it_formats_phone_number_to_whatsapp_jid(): void
    {
        $service = new WhatsAppService();

        $this->assertEquals('971501234567@c.us', $service->formatChatId('+971 50 123 4567'));
        $this->assertEquals('971501234567@c.us', $service->formatChatId('971501234567'));
        $this->assertEquals('971501234567@c.us', $service->formatChatId('971-50-123-4567'));
    }

    /** @test */
    public function it_sends_text_message_via_openwa_api(): void
    {
        Http::fake([
            '*/api/sessions/*/messages/send-text' => Http::response([
                'messageId' => 'wamid-123',
                'status' => 'sent',
            ], 201),
        ]);

        $service = new WhatsAppService();
        $result = $service->sendText('+971501234567', 'Hello from Lottly!');

        $this->assertEquals('wamid-123', $result['messageId']);
        $this->assertEquals('sent', $result['status']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-API-Key', 'test-api-key')
                && $request['chatId'] === '971501234567@c.us'
                && $request['text'] === 'Hello from Lottly!';
        });
    }

    /** @test */
    public function it_gets_session_status(): void
    {
        Http::fake([
            '*/api/sessions/*' => Http::response([
                'id' => 'test-session-uuid',
                'name' => 'property-manager-1',
                'status' => 'ready',
            ], 200),
        ]);

        $service = new WhatsAppService();
        $status = $service->getSessionStatus();

        $this->assertEquals('ready', $status['status']);
    }

    /** @test */
    public function it_gets_chat_history(): void
    {
        Http::fake([
            '*/messages/history*' => Http::response([
                'messages' => [
                    ['id' => '1', 'body' => 'Hello', 'from' => '971501234567@c.us'],
                    ['id' => '2', 'body' => 'Hi there!', 'from' => 'me'],
                ],
            ], 200),
        ]);

        $service = new WhatsAppService();
        $history = $service->getChatHistory('+971501234567');

        $this->assertCount(2, $history['messages']);
        $this->assertEquals('Hello', $history['messages'][0]['body']);
    }

    /** @test */
    public function it_throws_exception_on_api_failure(): void
    {
        Http::fake([
            '*/api/sessions/*/messages/send-text' => Http::response([
                'message' => 'Session not ready',
            ], 409),
        ]);

        $service = new WhatsAppService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenWA API error (409)');

        $service->sendText('+971501234567', 'Hello');
    }

    /** @test */
    public function it_creates_a_new_session(): void
    {
        Http::fake([
            '*/api/sessions' => Http::response([
                'id' => 'new-session-uuid',
                'name' => 'property-manager-2',
                'status' => 'created',
            ], 201),
        ]);

        $service = new WhatsAppService();
        $session = $service->createSession('property-manager-2');

        $this->assertEquals('new-session-uuid', $session['id']);
        $this->assertEquals('created', $session['status']);
    }

    /** @test */
    public function it_starts_a_session(): void
    {
        Http::fake([
            '*/api/sessions/*/start' => Http::response([
                'id' => 'test-session-uuid',
                'status' => 'initializing',
            ], 200),
        ]);

        $service = new WhatsAppService();
        $result = $service->startSession();

        $this->assertEquals('initializing', $result['status']);
    }

    /** @test */
    public function status_reports_not_connected_when_the_gateway_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('gateway down'));

        $response = $this->withToken('hardcoded-admin-token-test')
            ->getJson('/api/whatsapp/status');

        // A dead gateway is a 503, not a 500: nothing is wrong with the app.
        $response->assertStatus(503);
        $this->assertFalse($response->json('connected'));
    }

    /** @test */
    public function send_returns_a_failure_payload_rather_than_throwing(): void
    {
        Http::fake([
            '*/api/sessions/*/messages/send-text' => Http::response(['message' => 'Session not ready'], 409),
        ]);

        $response = $this->withToken('hardcoded-admin-token-test')
            ->postJson('/api/whatsapp/send', [
                'phone' => '+971501234567',
                'message' => 'Hello',
            ]);

        $response->assertStatus(500);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function send_validates_its_input_before_reaching_the_gateway(): void
    {
        Http::fake();

        $response = $this->withToken('hardcoded-admin-token-test')
            ->postJson('/api/whatsapp/send', [
                'phone' => '+971501234567',
                // Over OpenWA's 4096-character limit.
                'message' => str_repeat('a', 4097),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('message');

        Http::assertNothingSent();
    }

    /** @test */
    public function the_stream_route_rejects_an_unauthenticated_caller(): void
    {
        $response = $this->getJson('/api/whatsapp/stream');

        $response->assertStatus(401);
    }
}
