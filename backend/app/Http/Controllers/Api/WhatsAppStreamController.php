<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppStreamController extends Controller
{
    /**
     * GET /api/whatsapp/stream
     * Server-Sent Events endpoint for real-time message updates.
     *
     * The frontend connects to this endpoint via EventSource and receives
     * `message` events whenever a new inbound WhatsApp message arrives.
     */
    public function stream(Request $request)
    {
        // EventSource cannot set an Authorization header, so this route cannot
        // sit behind the Bearer-token middleware the other WhatsApp routes use.
        // Refuse an unauthenticated caller up front instead of leaking every
        // tenant's inbound message to whoever opens the socket.
        if (! $request->user() && ! $this->hasStreamCredential($request)) {
            abort(401, 'Unauthenticated.');
        }

        // Bound the connection so a forgotten browser tab cannot pin a worker
        // forever. The client reconnects automatically via EventSource.
        $deadline = time() + (int) config('services.openwa.stream_max_seconds', 300);

        $response = new StreamedResponse(function () use ($deadline) {
            $lastEventId = 0;

            // Disable output buffering for streaming
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            while (time() < $deadline) {
                // Check for new inbound messages
                $inboundMessages = Cache::get('whatsapp:inbound:messages', []);
                $newMessages = array_slice($inboundMessages, $lastEventId);

                if (! empty($newMessages)) {
                    $lastEventId = count($inboundMessages);

                    echo 'event: message'."\n";
                    echo 'data: '.json_encode($newMessages)."\n\n";
                }

                // Send heartbeat with session status
                $status = Cache::get('whatsapp:session_status', 'unknown');
                echo 'event: heartbeat'."\n";
                echo 'data: '.json_encode(['status' => $status, 'timestamp' => now()->toIso8601String()])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // Stop if the client disconnected
                if (connection_aborted()) {
                    break;
                }

                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable Nginx buffering

        return $response;
    }

    /**
     * EventSource cannot set an Authorization header, so the token arrives as a
     * `?token=` query parameter. Accept the same two forms the rest of the API
     * accepts: a real Sanctum personal access token, or the development
     * hardcoded-admin token handled by HandleHardcodedToken.
     */
    private function hasStreamCredential(Request $request): bool
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        if (str_starts_with($token, 'hardcoded-admin-token-')) {
            return true;
        }

        return Auth::guard('sanctum')->getSanctumUserByToken($token) !== null;
    }
}
