<?php

return [



    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],



    'openwa' => [
        'base_url' => env('OPENWA_BASE_URL', 'http://openwa:2785'),
        'api_key' => env('OPENWA_API_KEY', 'dev-admin-key'),
        'session_id' => env('OPENWA_SESSION_ID', ''),
        'webhook_secret' => env('OPENWA_WEBHOOK_SECRET', ''),



        'stream_max_seconds' => (int) env('OPENWA_STREAM_MAX_SECONDS', 300),
    ],

];
