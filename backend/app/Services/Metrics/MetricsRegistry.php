<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Log;
use PDO;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use RuntimeException;
use Throwable;

class MetricsRegistry
{
    private ?CollectorRegistry $registry = null;

    private ?PDO $pdo = null;


    private array $metrics = [];

    public function registry(): CollectorRegistry
    {
        return $this->registry ??= new CollectorRegistry($this->storage());
    }


    private function storage(): Adapter
    {
        if ($this->pdo instanceof PDO) {
            return new SqliteStorage($this->pdo, 'propertylease_metrics_');
        }

        try {
            $path = config('prometheus.storage_path');

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('PROMETHEUS_STORAGE_PATH is not configured');
            }

            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $this->pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);



            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            $this->pdo->exec('PRAGMA journal_mode = WAL');

            return new SqliteStorage($this->pdo, 'propertylease_metrics_');
        } catch (Throwable $e) {
            Log::warning('Prometheus storage unavailable, falling back to in-memory metrics.', [
                'error' => $e->getMessage(),
            ]);

            return new InMemory();
        }
    }



    public function httpRequests(): \Prometheus\Counter
    {
        return $this->metrics['http_requests'] ??= $this->registry()->getOrRegisterCounter(
            $this->namespace(),
            'http_requests_total',
            'Total HTTP requests handled by the API.',
            ['method', 'route', 'status']
        );
    }

    public function httpRequestDuration(): \Prometheus\Histogram
    {
        return $this->metrics['http_request_duration'] ??= $this->registry()->getOrRegisterHistogram(
            $this->namespace(),
            'http_request_duration_seconds',
            'Wall clock time spent serving an HTTP request, in seconds.',
            ['method', 'route', 'status'],
            config('prometheus.request_duration_buckets')
        );
    }

    public function httpResponseSize(): \Prometheus\Histogram
    {
        return $this->metrics['http_response_size'] ??= $this->registry()->getOrRegisterHistogram(
            $this->namespace(),
            'http_response_size_bytes',
            'Size of the HTTP response body, in bytes.',
            ['method', 'route'],
            config('prometheus.response_size_buckets')
        );
    }

    public function httpExceptions(): \Prometheus\Counter
    {
        return $this->metrics['http_exceptions'] ??= $this->registry()->getOrRegisterCounter(
            $this->namespace(),
            'http_exceptions_total',
            'Uncaught exceptions that turned an API request into a 5xx.',
            ['route', 'exception']
        );
    }



    public function databaseQueries(): \Prometheus\Histogram
    {
        return $this->metrics['database_queries'] ??= $this->registry()->getOrRegisterHistogram(
            $this->namespace(),
            'database_query_duration_seconds',
            'Time spent executing a database query, in seconds.',
            ['connection', 'operation'],
            config('prometheus.request_duration_buckets')
        );
    }

    public function databaseQueryCount(): \Prometheus\Counter
    {
        return $this->metrics['database_query_count'] ??= $this->registry()->getOrRegisterCounter(
            $this->namespace(),
            'database_queries_total',
            'Number of database queries executed while serving a request.',
            ['connection']
        );
    }

    public function databaseQueriesByOperation(): \Prometheus\Counter
    {
        return $this->metrics['database_queries_by_operation'] ??= $this->registry()->getOrRegisterCounter(
            $this->namespace(),
            'database_queries_by_operation_total',
            'Number of database queries executed while serving a request, by statement type.',
            ['connection', 'operation']
        );
    }



    public function processMemory(): \Prometheus\Gauge
    {
        return $this->metrics['process_memory'] ??= $this->registry()->getOrRegisterGauge(
            $this->namespace(),
            'process_memory_bytes',
            'Resident memory used by the PHP worker that served the last request.'
        );
    }

    public function uptime(): \Prometheus\Gauge
    {
        return $this->metrics['uptime'] ??= $this->registry()->getOrRegisterGauge(
            $this->namespace(),
            'process_start_time_seconds',
            'Unix timestamp at which the PHP worker handling this scrape started.'
        );
    }



    public function recordCount(string $entity, int $total): void
    {
        $this->registry()->getOrRegisterGauge(
            $this->namespace(),
            $entity.'_total',
            "Total number of {$entity} records."
        )->set($total);
    }


    public function recordCountByStatus(string $entity, array $countsByStatus): void
    {
        $gauge = $this->registry()->getOrRegisterGauge(
            $this->namespace(),
            $entity.'_total',
            "Total number of {$entity} records.",
            ['status']
        );

        foreach ($countsByStatus as $status => $count) {
            $gauge->set((int) $count, [(string) $status]);
        }
    }

    public function recordGauge(string $name, string $help, float $value): void
    {
        $this->registry()->getOrRegisterGauge(
            $this->namespace(),
            $name,
            $help
        )->set($value);
    }

    private function namespace(): string
    {
        return config('prometheus.namespace', 'propertylease');
    }
}
