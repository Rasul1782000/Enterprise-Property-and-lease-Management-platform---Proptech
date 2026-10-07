<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third-Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third-party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | OpenWA WhatsApp Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for the OpenWA WhatsApp API Gateway. OpenWA runs as a
    | Docker container alongside Floci and provides a REST API for sending
    | and receiving WhatsApp messages.
    |
    | base_url: The OpenWA API endpoint (no trailing slash)
    | api_key:  The X-API-Key for OpenWA authentication
    | session_id: The default WhatsApp session UUID
    | webhook_secret: HMAC secret for verifying inbound webhook signatures
    |
    */
    'openwa' => [
        'base_url' => env('OPENWA_BASE_URL', 'http://openwa:2785'),
        'api_key' => env('OPENWA_API_KEY', 'dev-admin-key'),
        'session_id' => env('OPENWA_SESSION_ID', ''),
        'webhook_secret' => env('OPENWA_WEBHOOK_SECRET', ''),

        // Bounds one SSE connection so an abandoned browser tab cannot pin a
        // PHP worker indefinitely. EventSource reconnects on its own.
        'stream_max_seconds' => (int) env('OPENWA_STREAM_MAX_SECONDS', 300),
    ],

];
