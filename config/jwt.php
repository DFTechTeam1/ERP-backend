<?php

/**
 * Centralized authentication token configuration.
 *
 * Laravel is the sole issuer of RS256 access tokens. Keys are loaded from
 * base64-encoded env vars so they survive newlines and fit the Dockerized
 * deployment. A SEPARATE keypair must be used per environment — never reuse
 * keys across local / staging / production.
 *
 * Only the public key is distributed to the non-Laravel services; they verify
 * tokens locally with it (no callback to Laravel per request).
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Signing keys (RS256)
    |--------------------------------------------------------------------------
    | Stored base64-encoded in the environment, decoded here at load time.
    | Generate a keypair with `php artisan jwt:keygen`.
    */
    'private_key' => env('JWT_PRIVATE_KEY') ? base64_decode((string) env('JWT_PRIVATE_KEY')) : null,
    'public_key' => env('JWT_PUBLIC_KEY') ? base64_decode((string) env('JWT_PUBLIC_KEY')) : null,

    /*
    |--------------------------------------------------------------------------
    | Token claims
    |--------------------------------------------------------------------------
    */
    'issuer' => env('JWT_ISSUER', 'https://auth.dfactory.pro'),
    'audience' => env('JWT_AUDIENCE', 'erp'),

    /*
    |--------------------------------------------------------------------------
    | Service account
    |--------------------------------------------------------------------------
    | Email of the ERP user that internal services (e.g. erp-backend-node) mint a
    | centralized token for, via the HMAC-protected
    | /api/internal/auth/service-token endpoint. Lets service-to-service calls
    | present a valid RS256 token without exchanging a password. Should be a
    | real, low-privilege ERP user.
    */
    'service_account_email' => env('JWT_SERVICE_ACCOUNT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Key id (kid) header
    |--------------------------------------------------------------------------
    | Set on every issued token so verifiers can pick the right key during a
    | future rotation via JWKS. Start at "key-1".
    */
    'kid' => env('JWT_KID', 'key-1'),

    /*
    |--------------------------------------------------------------------------
    | Lifetimes
    |--------------------------------------------------------------------------
    | Access token is short-lived (minutes). Refresh tokens use a sliding
    | window: default vs. "remember me" (days).
    */
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 30), // minutes
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1), // days
    'refresh_ttl_remember' => (int) env('JWT_REFRESH_TTL_REMEMBER', 30), // days

    /*
    |--------------------------------------------------------------------------
    | Refresh rotation safety
    |--------------------------------------------------------------------------
    | Grace window (seconds) during which a just-rotated token replayed by a
    | near-simultaneous request is treated as a benign race rather than theft.
    | Prune deletes revoked/expired rows older than `prune_after` days.
    */
    'refresh_grace_seconds' => (int) env('JWT_REFRESH_GRACE_SECONDS', 10),
    'prune_after' => (int) env('JWT_REFRESH_PRUNE_AFTER', 7), // days past expiry

    /*
    |--------------------------------------------------------------------------
    | Refresh cookie
    |--------------------------------------------------------------------------
    | Delivered as an httpOnly cookie (XSS-safe). For a cross-site frontend set
    | same_site=none, secure=true and a shared parent domain (e.g. .dfactory.pro).
    */
    'refresh_cookie' => env('JWT_REFRESH_COOKIE', 'df_refresh_token'),
    'refresh_cookie_domain' => env('JWT_REFRESH_COOKIE_DOMAIN'),
    'refresh_cookie_path' => env('JWT_REFRESH_COOKIE_PATH', '/'),
    'refresh_cookie_secure' => (bool) env('JWT_REFRESH_COOKIE_SECURE', true),
    'refresh_cookie_same_site' => env('JWT_REFRESH_COOKIE_SAME_SITE', 'lax'),
];
