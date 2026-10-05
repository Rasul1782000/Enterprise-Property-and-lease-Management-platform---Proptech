<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Set to "documents" to route lease PDFs and property photos through S3.
    | In CI that resolves to the local Floci emulator, so no real AWS account
    | or bucket is ever required to run the test suite.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        /*
        | Raw S3 disk. In development and CI, AWS_ENDPOINT_URL points at Floci
        | (http://floci:4566 from inside the Jenkins container,
        | http://localhost:4566 from the host). Leave it unset in production to
        | talk to the real AWS endpoint derived from the region.
        */
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_STORAGE_BUCKET', 'property-lease-documents'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT_URL'),
            // S3-compatible emulators only serve path-style URLs; real AWS does too.
            'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            // Surface failures instead of silently returning false, so CI fails loudly.
            'throw' => true,
        ],

        /*
        | Application disk for lease agreements and property photos. Identical
        | to the "s3" disk but namespaced under a prefix so other consumers can
        | share the bucket without colliding.
        */
        'documents' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_STORAGE_BUCKET', 'property-lease-documents'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT_URL'),
            'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'root' => env('AWS_STORAGE_PREFIX', 'portal'),
            'throw' => true,
        ],

    ],

];
