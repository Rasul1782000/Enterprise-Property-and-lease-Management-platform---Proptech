<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{

    public function handle(Request $request)
    {


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


        match ($event['event'] ?? '') {
            'message' => $this->handleInboundMessage($event),
            'session.status' => $this->handleSessionStatus($event),
            default => null,
        };

        return response()->json(['received' => true]);
    }


    protected function handleInboundMessage(array $event): void
    {
        $message = $event['data'] ?? [];
        $from = $message['from'] ?? '';
        $body = $message['body'] ?? '';
        $timestamp = $message['timestamp'] ?? now()->toIso8601String();


        $cacheKey = 'whatsapp:inbound:messages';
        $messages = Cache::get($cacheKey, []);
        $messages[] = [
            'from' => $from,
            'body' => $body,
            'timestamp' => $timestamp,
            'direction' => 'inbound',
        ];


        if (count($messages) > 100) {
            $messages = array_slice($messages, -100);
        }

        Cache::put($cacheKey, $messages, now()->addHours(24));


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


    protected function handleSessionStatus(array $event): void
    {
        $status = $event['data']['status'] ?? 'unknown';

        Cache::put('whatsapp:session_status', $status, now()->addHour());

        Log::info('WhatsApp session status changed', ['status' => $status]);
    }


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
