<?php

namespace App\Http\Middleware;

use App\Services\Metrics\MetricsRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordMetrics
{
    public function __construct(private MetricsRegistry $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('prometheus.enabled', true) || $this->shouldSkip($request)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $queryCount = 0;
        $querySeconds = 0.0;
        $queryCountByOperation = [];
        $querySecondsByOperation = [];




        DB::listen(function ($query) use (&$queryCount, &$querySeconds, &$queryCountByOperation, &$querySecondsByOperation): void {
            $queryCount++;
            $seconds = ($query->time ?? 0) / 1000;
            $querySeconds += $seconds;

            $operation = $this->queryOperation($query->sql);
            $queryCountByOperation[$operation] = ($queryCountByOperation[$operation] ?? 0) + 1;
            $querySecondsByOperation[$operation] = ($querySecondsByOperation[$operation] ?? 0.0) + $seconds;
        });

        $response = null;

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->recordException($request, $e);
            $this->record($request, null, $startedAt, $queryCount, $querySeconds, $queryCountByOperation, $querySecondsByOperation);

            throw $e;
        }

        $this->record($request, $response, $startedAt, $queryCount, $querySeconds, $queryCountByOperation, $querySecondsByOperation);

        return $response;
    }


    private function queryOperation(string $sql): string
    {
        $verb = strtolower(ltrim($sql));

        foreach (['select', 'insert', 'update', 'delete'] as $keyword) {
            if (str_starts_with($verb, $keyword)) {
                return $keyword;
            }
        }

        return 'other';
    }


    private function record(
        Request $request,
        ?Response $response,
        int $startedAt,
        int $queryCount,
        float $querySeconds,
        array $queryCountByOperation = [],
        array $querySecondsByOperation = []
    ): void {
        try {
            $duration = (hrtime(true) - $startedAt) / 1e9;




            $route = $request->route()?->uri() ?? $request->path();
            $method = $request->method();
            $status = (string) ($response?->getStatusCode() ?? 500);

            $this->registry->httpRequests()->inc([$method, $route, $status]);
            $this->registry->httpRequestDuration()->observe($duration, [$method, $route, $status]);

            if ($response !== null) {
                $this->registry->httpResponseSize()->observe(
                    strlen((string) $response->getContent()),
                    [$method, $route]
                );
            }





            $this->registry->databaseQueryCount()->incBy($queryCount, ['default']);

            foreach ($queryCountByOperation as $operation => $count) {
                $this->registry->databaseQueriesByOperation()->incBy($count, ['default', $operation]);
                $this->registry->databaseQueries()->observe(
                    $querySecondsByOperation[$operation] ?? 0.0,
                    ['default', $operation]
                );
            }

            $this->recordRuntime();
        } catch (Throwable $e) {
            Log::warning('Failed to record request metrics.', ['error' => $e->getMessage()]);
        }
    }

    private function recordException(Request $request, Throwable $e): void
    {
        try {
            $this->registry->httpExceptions()->inc([
                $request->route()?->uri() ?? $request->path(),
                class_basename($e),
            ]);
        } catch (Throwable $inner) {
            Log::warning('Failed to record exception metric.', ['error' => $inner->getMessage()]);
        }
    }

    private function recordRuntime(): void
    {
        $this->registry->processMemory()->set(memory_get_usage(true));



        $this->registry->uptime()->set((float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)));
    }


    private function shouldSkip(Request $request): bool
    {
        return in_array($request->path(), config('prometheus.ignored_paths', []), true);
    }
}
