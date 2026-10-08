<?php

return [



    'enabled' => env('PROMETHEUS_ENABLED', true),



    'namespace' => env('PROMETHEUS_NAMESPACE', 'propertylease'),



    'storage' => env('PROMETHEUS_STORAGE', 'pdo'),



    'storage_path' => env(
        'PROMETHEUS_STORAGE_PATH',
        storage_path('prometheus/metrics.sqlite')
    ),



    'ignored_paths' => [
        'metrics',
        'up',
    ],



    'request_duration_buckets' => [
        0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10,
    ],



    'response_size_buckets' => [
        256, 1024, 4096, 16384, 65536, 262144, 1048576, 5242880,
    ],



    'business_metrics_ttl' => (int) env('PROMETHEUS_BUSINESS_TTL', 60),

];
