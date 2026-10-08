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


    public function sendText(string $phone, string $message): array
    {
        $response = $this->request('POST', "/api/sessions/{$this->sessionId}/messages/send-text", [
            'chatId' => $this->formatChatId($phone),
            'text' => $message,
        ]);

        return $response;
    }


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


    public function getSessionStatus(): array
    {
        return $this->request('GET', "/api/sessions/{$this->sessionId}");
    }


    public function getChatHistory(string $phone, int $limit = 50): array
    {
        $chatId = $this->formatChatId($phone);

        return $this->request('GET', "/api/sessions/{$this->sessionId}/messages/history", [
            'chatId' => $chatId,
            'limit' => $limit,
        ]);
    }


    public function getQrCode(): array
    {
        return $this->request('GET', "/api/sessions/{$this->sessionId}/qr");
    }


    public function startSession(): array
    {
        return $this->request('POST', "/api/sessions/{$this->sessionId}/start");
    }


    public function createSession(string $name): array
    {
        return $this->request('POST', '/api/sessions', [
            'name' => $name,
        ]);
    }


    public function listSessions(): array
    {
        return $this->request('GET', '/api/sessions');
    }


    public function formatChatId(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        return "{$cleaned}@c.us";
    }


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
