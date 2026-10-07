<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $sessionId;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.openwa.base_url', 'http://openwa:2785'), '/');
        $this->apiKey = config('services.openwa.api_key', 'dev-admin-key');
        $this->sessionId = config('services.openwa.session_id', '');
    }

    /**
     * Send a text message to a phone number.
     *
     * @return array{messageId: string, status: string}
     * @throws \Exception
     */
    public function sendText(string $phone, string $message): array
    {
        $response = $this->request('POST', "/api/sessions/{$this->sessionId}/messages/send-text", [
            'chatId' => $this->formatChatId($phone),
            'text' => $message,
        ]);

        return $response;
    }

    /**
     * Send a document (PDF, image, etc.) to a phone number.
     *
     * @return array{messageId: string, status: string}
     * @throws \Exception
     */
    public function sendDocument(string $phone, string $documentPath, string $caption = ''): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->attach(
            'file',
            file_get_contents($documentPath),
            basename($documentPath)
        )->post("{$this->baseUrl}/api/sessions/{$this->sessionId}/messages/send-document", [
            'chatId' => $this->formatChatId($phone),
            'caption' => $caption,
        ]);

        if ($response->failed()) {
            Log::error('OpenWA send document failed', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);
            throw new \Exception('Failed to send WhatsApp document: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get the current session status.
     *
     * @return array<string, mixed>
     */
    public function getSessionStatus(): array
    {
        return $this->request('GET', "/api/sessions/{$this->sessionId}");
    }

    /**
     * Get chat history for a given phone number.
     *
     * @return array{messages: array<int, array<string, mixed>>}
     */
    public function getChatHistory(string $phone, int $limit = 50): array
    {
        $chatId = $this->formatChatId($phone);

        return $this->request('GET', "/api/sessions/{$this->sessionId}/messages/history", [
            'chatId' => $chatId,
            'limit' => $limit,
        ]);
    }

    /**
     * Get the QR code for session linking.
     *
     * @return array{qrCode: string, status: string}
     */
    public function getQrCode(): array
    {
        return $this->request('GET', "/api/sessions/{$this->sessionId}/qr");
    }

    /**
     * Start the WhatsApp session.
     */
    public function startSession(): array
    {
        return $this->request('POST', "/api/sessions/{$this->sessionId}/start");
    }

    /**
     * Create a new WhatsApp session.
     */
    public function createSession(string $name): array
    {
        return $this->request('POST', '/api/sessions', [
            'name' => $name,
        ]);
    }

    /**
     * List all WhatsApp sessions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(): array
    {
        return $this->request('GET', '/api/sessions');
    }

    /**
     * Format a phone number to WhatsApp JID (e.g., 971501234567@c.us).
     */
    public function formatChatId(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        return "{$cleaned}@c.us";
    }

    /**
     * Make an authenticated request to the OpenWA API.
     *
     * @return array<string, mixed>
     * @throws \Exception
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl.$path;

        $http = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(30);

        $response = match ($method) {
            'GET' => $http->get($url, $data),
            'POST' => $http->post($url, $data),
            'PUT' => $http->put($url, $data),
            'DELETE' => $http->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            $body = $response->json();
            $errorMsg = $body['message'] ?? $body['error'] ?? $response->body();

            Log::error('OpenWA API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'response' => $body,
            ]);

            throw new \Exception("OpenWA API error ({$response->status()}): {$errorMsg}");
        }

        return $response->json();
    }
}
