<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prometheus Metrics
    |--------------------------------------------------------------------------
    |
    | The portal exposes a Prometheus scrape endpoint at GET /metrics. Point the
    | Prometheus instance (running on the Docker bridge network) at it with:
    |
    |     http://host.docker.internal:8000/metrics
    |
    | See /observability/prometheus.yml in the repository root for the scrape
    | job and the Grafana provisioning files that render these series.
    |
    */

    'enabled' => env('PROMETHEUS_ENABLED', true),

    /*
    | Metric namespace. Every exported series is prefixed with this value, so
    | `http_requests_total` becomes `propertylease_http_requests_total`.
    */
    'namespace' => env('PROMETHEUS_NAMESPACE', 'propertylease'),

    /*
    | Storage adapter used to persist counters between requests.
    |
    |   redis     - shared storage, safe for multi-worker / multi-process setups
    |   inmemory  - per-process only, fine for a single dev server
    |
    | Redis is the default because PHP-FPM and `artisan serve` handle every
    | request in a fresh process; without shared storage each scrape would only
    | ever see the metrics of the process that served it.
    */
    'storage' => env('PROMETHEUS_STORAGE', 'redis'),

    /*
    | Redis connection used by the `redis` adapter above. The connection must be
    | reachable from the PHP process, not just from inside Docker.
    */
    'redis' => [
        'connection' => env('PROMETHEUS_REDIS_CONNECTION', 'default'),
        'prefix' => env('PROMETHEUS_REDIS_PREFIX', 'PROMETHEUS_'),
    ],

    /*
    | Paths that are never recorded as application traffic. The scrape endpoint
    | itself would otherwise inflate every rate and latency panel it feeds.
    */
    'ignored_paths' => [
        'metrics',
        'up',
    ],

    /*
    | Upper bounds (in seconds) of the request duration histogram. Grafana's
    | quantile panels are derived from these buckets, so the spread should match
    | the latency range this API actually serves.
    */
    'request_duration_buckets' => [
        0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10,
    ],

    /*
    | Upper bounds (in bytes) of the response size histogram.
    */
    'response_size_buckets' => [
        256, 1024, 4096, 16384, 65536, 262144, 1048576, 5242880,
    ],

    /*
    | Seconds between refreshes of the domain KPI gauges (properties, units,
    | occupancy, outstanding invoices). Counting rows on every scrape is cheap
    | at this data volume, but there is no reason to do it 4x a minute.
    */
    'business_metrics_ttl' => (int) env('PROMETHEUS_BUSINESS_TTL', 60),

];
