<?php

return [
    'apple_health_directory' => env('APPLE_HEALTH_DIRECTORY', storage_path('health')),
    'timezone' => 'Europe/Bucharest',
    'preview_minutes' => 30,
    'fetch_concurrency' => max(1, min(6, (int) env('HEALTH_FETCH_CONCURRENCY', 4))),
    'publish_concurrency' => max(1, min(6, (int) env('HEALTH_PUBLISH_CONCURRENCY', 4))),
    'withings_retry_attempts' => 3,
    'withings_retry_seconds' => 60,
    'withings_retry_budget_seconds' => 600,
    'console_lock_path' => storage_path('framework/health-publish.lock'),
    'oura' => [
        'client_id' => env('OURA_CLIENT_ID'),
        'client_secret' => env('OURA_CLIENT_SECRET'),
        'redirect_path' => env('OURA_REDIRECT_ROUTE', '/callback-oura'),
        'scopes' => ['daily', 'heartrate', 'workout', 'session', 'spo2', 'stress', 'heart_health'],
    ],
    'blogs' => [
        'local' => [
            'label' => 'Local',
            'url' => env('BLOG_LOCAL_URL', 'https://blog.test'),
            'username' => env('BLOG_LOCAL_USERNAME'),
            'password' => env('BLOG_LOCAL_APPLICATION_PASSWORD'),
        ],
        'production' => [
            'label' => 'Production',
            'url' => env('BLOG_PRODUCTION_URL', 'https://pacurar.dev'),
            'username' => env('BLOG_PRODUCTION_USERNAME'),
            'password' => env('BLOG_PRODUCTION_APPLICATION_PASSWORD'),
        ],
    ],
];
