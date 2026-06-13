<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth', 'storage/*', 'app/*'],

    'allowed_methods' => ['*'],

    /*
    | Centralized auth: the rotating refresh token is delivered as an httpOnly
    | cookie, so the frontend must send credentialed requests. Browsers reject
    | credentialed CORS against a wildcard origin, so when credentials are on,
    | set CORS_ALLOWED_ORIGINS to an explicit comma-separated list of origins
    | (e.g. https://staging.dfactory.pro). Defaults preserve the prior behavior.
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
