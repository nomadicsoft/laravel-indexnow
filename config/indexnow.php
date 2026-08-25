<?php

$appUrl = (string) env('APP_URL', 'https://localhost');
$defaultHost = strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost'));
$defaultScheme = strtolower((string) (parse_url($appUrl, PHP_URL_SCHEME) ?: 'https'));

return [
    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Keep this disabled until the verification key is publicly reachable.
    |
    */
    'enabled' => env('INDEXNOW_ENABLED', false),

    'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),

    'default_host' => $defaultHost,
    'default_scheme' => $defaultScheme,

    /*
    | Each hostname requires its own key. Subdomains are intentionally not
    | inherited. A null key_location becomes https://host/{key}.txt.
    */
    'hosts' => [
        $defaultHost => [
            'key' => env('INDEXNOW_KEY'),
            'key_location' => env('INDEXNOW_KEY_LOCATION'),
            'endpoint' => null,
            'scheme' => $defaultScheme,
        ],
    ],

    /* Protocol maximum: 10,000. A smaller default keeps queue payloads modest. */
    'batch_size' => (int) env('INDEXNOW_BATCH_SIZE', 500),

    'queue' => [
        'enabled' => env('INDEXNOW_QUEUE_ENABLED', true),
        'connection' => env('INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('INDEXNOW_QUEUE_NAME', 'default'),
        'after_commit' => true,
        'delay' => (int) env('INDEXNOW_QUEUE_DELAY', 5),
        'tries' => (int) env('INDEXNOW_QUEUE_TRIES', 5),
        'backoff' => [30, 120, 600, 1800],
        'timeout' => (int) env('INDEXNOW_QUEUE_TIMEOUT', 30),
    ],

    /* Claims are created by the job after commit, not by the web request. */
    'dedupe' => [
        'enabled' => env('INDEXNOW_DEDUPE_ENABLED', true),
        'store' => env('INDEXNOW_CACHE_STORE'),
        'ttl' => (int) env('INDEXNOW_DEDUPE_TTL', 300),
        'prefix' => 'indexnow:submitted:',
    ],

    'http' => [
        'connect_timeout' => (int) env('INDEXNOW_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('INDEXNOW_HTTP_TIMEOUT', 10),
        'user_agent' => 'nomadicsoft/laravel-indexnow',
        'response_body_limit' => 2048,
    ],

    'urls' => [
        'allowed_schemes' => ['http', 'https'],
        'max_length' => 2048,
        'exclude_paths' => [],
        'exclude_query_parameters' => [
            'fbclid',
            'gclid',
            'utm_*',
            'yclid',
        ],
    ],

    /* The fallback route is host-bound at runtime and only serves /{key}.txt. */
    'serve_key' => env('INDEXNOW_SERVE_KEY', true),
    'route' => [
        'middleware' => [],
        'name' => 'indexnow.key',
    ],

    'model' => [
        'events' => ['created', 'updated', 'deleted', 'restored'],
        'fail_silently' => true,
    ],

    'sitemap' => [
        'max_documents' => 100,
        'max_bytes_per_document' => 10 * 1024 * 1024,
    ],
];
