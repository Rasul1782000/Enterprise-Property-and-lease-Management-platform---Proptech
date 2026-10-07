<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    /**
     * POST /api/whatsapp/webhook
     * Receives inbound messages and events from OpenWA.
     */
    public function handle(Request $request)
    {
        /*
         * Order matters. With no secret configured there is nothing to verify
         * against, so this route is unauthenticated by definition and must
         * refuse rather than trust the caller. Checking the signature first
         * would report this misconfiguration as a bad signature, hiding the
         * real problem.
         */
        if (empty(config('services.openwa.webhook_secret'))) {
            Log::warning('OpenWA webhook received but OPENWA_WEBHOOK_SECRET is not configured', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Webhook signature verification is not configured.',
            ], 503);
        }

        $signature = $request->header('X-OpenWA-Signature');
        $payload = $request->getContent();

        if (! $this->verifySignature($payload, $signature)) {
            Log::warning('OpenWA webhook received with invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->json()->all();

        Log::info('OpenWA webhook received', [
            'event' => $event['event'] ?? 'unknown',
        ]);

        // Handle different event types
        match ($event['event'] ?? '') {
            'message' => $this->handleInboundMessage($event),
            'session.status' => $this->handleSessionStatus($event),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * Handle an inbound WhatsApp message.
     */
    protected function handleInboundMessage(array $event): void
    {
        $message = $event['data'] ?? [];
        $from = $message['from'] ?? '';
        $body = $message['body'] ?? '';
        $timestamp = $message['timestamp'] ?? now()->toIso8601String();

        // Store in cache for real-time retrieval via SSE
        $cacheKey = 'whatsapp:inbound:messages';
        $messages = Cache::get($cacheKey, []);
        $messages[] = [
            'from' => $from,
            'body' => $body,
            'timestamp' => $timestamp,
            'direction' => 'inbound',
        ];

        // Keep only the last 100 messages in cache
        if (count($messages) > 100) {
            $messages = array_slice($messages, -100);
        }

        Cache::put($cacheKey, $messages, now()->addHours(24));

        // Also store per-phone for easy lookup
        $phoneKey = 'whatsapp:inbound:'.md5($from);
        $phoneMessages = Cache::get($phoneKey, []);
        $phoneMessages[] = [
            'from' => $from,
            'body' => $body,
            'timestamp' => $timestamp,
            'direction' => 'inbound',
        ];
        Cache::put($phoneKey, $phoneMessages, now()->addHours(24));

        Log::info('WhatsApp inbound message stored', [
            'from' => $from,
            'body' => substr($body, 0, 100),
        ]);
    }

    /**
     * Handle a session status change event.
     */
    protected function handleSessionStatus(array $event): void
    {
        $status = $event['data']['status'] ?? 'unknown';

        Cache::put('whatsapp:session_status', $status, now()->addHour());

        Log::info('WhatsApp session status changed', ['status' => $status]);
    }

    /**
     * Verify the HMAC signature from OpenWA.
     */
    protected function verifySignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.openwa.webhook_secret');

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
