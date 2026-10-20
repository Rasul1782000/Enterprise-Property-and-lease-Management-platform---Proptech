<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:4200'),
        'https://frontend-gamma-hazel-80.vercel.app',
        'https://frontend-rasul-ahmed-khans-projects.vercel.app',
        'http://localhost:4200',
        'http://127.0.0.1:4200',
    ],
    'allowed_origins_patterns' => [
        '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
