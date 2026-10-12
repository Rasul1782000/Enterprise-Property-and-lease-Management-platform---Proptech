<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DebugMiddleware
{
    private float $startTime;
    private int $startMemory;

    public function handle(Request $request, Closure $next): Response
    {
        // Everything this middleware does is diagnostic, so when debugging is
        // off it must be a genuine pass-through: no logging, no header parsing,
        // no query counting. Writing request/response bodies to disk in
        // production would be both a disk and a data-exposure hazard.
        if (! $this->isDebugEnabled()) {
            return $next($request);
        }

        $this->startTime = hrtime(true);
        $this->startMemory = memory_get_usage(true);

        $requestId = uniqid('req_', true);

        // ── Log incoming request ────────────────────────────────────────────
        $this->logRequest($request, $requestId);

        // ── Process request ────────────────────────────────────────────────
        $response = $next($request);

        // ── Log outgoing response ──────────────────────────────────────────
        $this->logResponse($request, $response, $requestId);

        // ── Add debug headers ──────────────────────────────────────────────
        if ($this->isDebugEnabled()) {
            $response->headers->set('X-Debug-Request-Id', $requestId);
            $response->headers->set('X-Debug-Duration-Ms', round($this->getDurationMs(), 2));
            $response->headers->set('X-Debug-Memory-MB', round($this->getMemoryMb(), 2));
            $response->headers->set('X-Debug-Db-Queries', $this->getQueryCount());
        }

        return $response;
    }

    private function logRequest(Request $request, string $requestId): void
    {
        $context = [
            'request_id'   => $requestId,
            'method'       => $request->method(),
            'url'          => $request->fullUrl(),
            'path'         => $request->path(),
            'ip'           => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'headers'      => $this->filterHeaders($request->headers->all()),
            'query_params' => $request->query(),
            'body'         => $this->filterBody($request->all()),
            'files'        => array_keys($request->files->all()),
            'user_id'      => $request->user()?->id,
            'timestamp'    => now()->toIso8601String(),
        ];

        Log::channel('debug')->debug('→ Incoming Request', $context);
    }

    private function logResponse(Request $request, Response $response, string $requestId): void
    {
        $durationMs = $this->getDurationMs();
        $memoryMb   = $this->getMemoryMb();

        $content = $response->getContent();
        $bodyPreview = null;

        if (is_string($content) && strlen($content) > 0) {
            $decoded = json_decode($content, true);
            if ($decoded !== null) {
                $bodyPreview = $this->truncateArray($decoded, 500);
            } else {
                $bodyPreview = mb_substr($content, 0, 500);
            }
        }

        $context = [
            'request_id'      => $requestId,
            'method'          => $request->method(),
            'url'             => $request->fullUrl(),
            'status_code'     => $response->getStatusCode(),
            'status_text'     => Response::$statusTexts[$response->getStatusCode()] ?? 'Unknown',
            'duration_ms'     => round($durationMs, 2),
            'memory_mb'       => round($memoryMb, 2),
            'db_queries'      => $this->getQueryCount(),
            'response_size'   => $this->formatBytes(strlen($content)),
            'response_body'   => $bodyPreview,
            'response_headers' => $this->filterHeaders($response->headers->all()),
            'timestamp'       => now()->toIso8601String(),
        ];

        $level = 'debug';
        if ($response->getStatusCode() >= 500) {
            $level = 'error';
        } elseif ($response->getStatusCode() >= 400) {
            $level = 'warning';
        }

        Log::channel('debug')->{$level}('← Outgoing Response', $context);
    }

    private function getDurationMs(): float
    {
        return (hrtime(true) - $this->startTime) / 1e6; // nanoseconds to milliseconds
    }

    private function getMemoryMb(): float
    {
        return (memory_get_usage(true) - $this->startMemory) / 1048576;
    }

    private function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    private function isDebugEnabled(): bool
    {
        // The dedicated debug.enabled switch is authoritative. Falling back to
        // app.debug would force headers on for every request whenever
        // APP_DEBUG=true, making the switch impossible to turn off.
        if (config()->has('debug.enabled')) {
            return (bool) config('debug.enabled');
        }

        return (bool) config('app.debug', false);
    }

    private function filterHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token'];

        // Substring matched, so set-cookie / X-CSRF-Token style variants and
        // proxy-prefixed duplicates are all caught, not just exact matches.
        $needles = ['authorization', 'cookie', 'csrf', 'xsrf', 'token', 'secret', 'api-key', 'apikey'];

        $filtered = [];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower((string) $key);

            $isSensitive = in_array($lowerKey, $sensitive, true);

            if (! $isSensitive) {
                foreach ($needles as $needle) {
                    if (str_contains($lowerKey, $needle)) {
                        $isSensitive = true;
                        break;
                    }
                }
            }

            $filtered[$key] = $isSensitive ? ['***REDACTED***'] : $values;
        }

        return $filtered;
    }

    private function filterBody(array $body): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'authorization', 'card', 'cvv', 'cvc', 'pin', 'otp'];
        $filtered        = [];

        foreach ($body as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            foreach ($sensitiveFields as $field) {
                if (str_contains($lowerKey, $field)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $filtered[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterBody($value);
            } elseif (is_string($value) && strlen($value) > 200) {
                $filtered[$key] = mb_substr($value, 0, 200) . '...';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function truncateArray(array $data, int $maxLength): ?string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }
        if (strlen($json) > $maxLength) {
            return mb_substr($json, 0, $maxLength) . '... (truncated)';
        }
        return $json;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
