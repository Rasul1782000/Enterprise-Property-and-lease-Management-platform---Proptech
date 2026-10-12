<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, the DebugMiddleware will add debug headers to every
    | response and log detailed request/response information.
    |
    */
    'enabled' => env('DEBUG_ENABLED', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The logging channel to use for debug output. This maps to a channel
    | defined in config/logging.php.
    |
    */
    'channel' => 'debug',

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    */
    'log_requests' => [
        'enabled'       => true,
        'log_body'      => true,
        'log_headers'   => true,
        'log_query'     => true,
        'max_body_size' => 65536, // 64KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Logging
    |--------------------------------------------------------------------------
    */
    'log_responses' => [
        'enabled'       => true,
        'log_body'      => true,
        'max_body_size' => 65536,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Query Logging
    |--------------------------------------------------------------------------
    */
    'log_queries' => [
        'enabled'         => true,
        'log_slow_queries' => true,
        'slow_threshold'  => 100, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Profiling
    |--------------------------------------------------------------------------
    */
    'profiling' => [
        'enabled'           => true,
        'log_memory'        => true,
        'log_duration'      => true,
        'slow_request_ms'   => 1000, // Log warning if request takes longer
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Headers
    |--------------------------------------------------------------------------
    | These headers are added to responses when debug mode is enabled.
    |
    */
    'headers' => [
        'request_id'     => 'X-Debug-Request-Id',
        'duration_ms'    => 'X-Debug-Duration-Ms',
        'memory_mb'      => 'X-Debug-Memory-MB',
        'db_query_count' => 'X-Debug-Db-Queries',
    ],

];
