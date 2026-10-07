<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppController extends Controller
{
    protected WhatsAppService $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * GET /api/whatsapp/status
     * Check if the WhatsApp session is connected and ready.
     */
    public function status()
    {
        try {
            $status = $this->whatsapp->getSessionStatus();

            return response()->json([
                'connected' => ($status['status'] ?? '') === 'ready',
                'status' => $status,
            ]);
        } catch (Throwable $e) {
            Log::warning('WhatsApp status check failed', ['error' => $e->getMessage()]);

            return response()->json([
                'connected' => false,
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * POST /api/whatsapp/send
     * Send a text message to a tenant.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'message' => 'required|string|max:4096',
        ]);

        try {
            $result = $this->whatsapp->sendText(
                $validated['phone'],
                $validated['message']
            );

            return response()->json([
                'success' => true,
                'messageId' => $result['messageId'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('WhatsApp send failed', [
                'phone' => $validated['phone'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/whatsapp/history
     * Get chat history for a tenant.
     */
    public function history(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'limit' => 'integer|min:1|max:100',
        ]);

        try {
            $history = $this->whatsapp->getChatHistory(
                $validated['phone'],
                $validated['limit'] ?? 50
            );

            return response()->json([
                'success' => true,
                'messages' => $history['messages'] ?? [],
            ]);
        } catch (Throwable $e) {
            Log::error('WhatsApp history fetch failed', [
                'phone' => $validated['phone'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/whatsapp/qr
     * Get the QR code for linking a WhatsApp account.
     */
    public function qr()
    {
        try {
            $qr = $this->whatsapp->getQrCode();

            return response()->json([
                'success' => true,
                'qrCode' => $qr['qrCode'] ?? null,
                'status' => $qr['status'] ?? 'unknown',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/whatsapp/session/start
     * Start the WhatsApp session.
     */
    public function startSession()
    {
        try {
            $result = $this->whatsapp->startSession();

            return response()->json([
                'success' => true,
                'status' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/whatsapp/session/create
     * Create a new WhatsApp session.
     */
    public function createSession(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9-]+$/',
        ]);

        try {
            $session = $this->whatsapp->createSession($validated['name']);

            return response()->json([
                'success' => true,
                'session' => $session,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
