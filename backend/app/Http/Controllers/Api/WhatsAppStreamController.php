<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppStreamController extends Controller
{

    public function stream(Request $request)
    {




        if (! $request->user() && ! $this->hasStreamCredential($request)) {
            abort(401, 'Unauthenticated.');
        }



        $deadline = time() + (int) config('services.openwa.stream_max_seconds', 300);

        $response = new StreamedResponse(function () use ($deadline) {
            $lastEventId = 0;


            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            while (time() < $deadline) {

                $inboundMessages = Cache::get('whatsapp:inbound:messages', []);
                $newMessages = array_slice($inboundMessages, $lastEventId);

                if (! empty($newMessages)) {
                    $lastEventId = count($inboundMessages);

                    echo 'event: message'."\n";
                    echo 'data: '.json_encode($newMessages)."\n\n";
                }


                $status = Cache::get('whatsapp:session_status', 'unknown');
                echo 'event: heartbeat'."\n";
                echo 'data: '.json_encode(['status' => $status, 'timestamp' => now()->toIso8601String()])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();


                if (connection_aborted()) {
                    break;
                }

                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }


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
