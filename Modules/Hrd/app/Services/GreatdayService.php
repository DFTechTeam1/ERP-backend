<?php

namespace Modules\Hrd\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GreatdayService
{
    protected $accessKey;

    protected $accessSecret;

    protected $baseUrl;

    // Cache token key
    protected function getCacheTokenKey(): string
    {
        return 'greatday_token';
    }

    public function __construct()
    {
        $this->accessKey = config('app.greatday.access_key');
        $this->accessSecret = config('app.greatday.access_secret');
        $this->baseUrl = config('app.greatday.base_url');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Login to Greatday and store the token in cache.
     */
    public function login(): string
    {
        $check = Cache::get($this->getCacheTokenKey());

        if (! $check) {
            // No token, request new token
            $this->requestToken();
        }

        // Check if access token is expired, if expired refresh token
        $this->checkAccessToken();

        return Cache::get($this->getCacheTokenKey())['access_token'] ?? '';
    }

    /**
     * Send an authenticated POST to Greatday, transparently re-authenticating once
     * if the cached token is rejected.
     *
     * Tokens are cached for up to 8 days keyed only on their own stated expiry, so
     * a stale or malformed cached token (e.g. a truncated access_token) would make
     * every call 401 until that TTL lapses. On a 401 we drop the cached token and
     * retry with a freshly issued one, so the integration self-heals instead of
     * staying stuck.
     *
     * @param  array<string, mixed>  $body
     */
    public function authedPost(string $path, array $body): Response
    {
        $response = Http::withToken($this->login())->post($this->baseUrl.$path, $body);

        if ($response->status() === 401) {
            Cache::forget($this->getCacheTokenKey());
            $response = Http::withToken($this->login())->post($this->baseUrl.$path, $body);
        }

        return $response;
    }

    /**
     * Send an authenticated PUT to Greatday, transparently re-authenticating once if the cached
     * token is rejected. Same self-healing rationale as authedPost.
     *
     * @param  array<int, array<string, mixed>>  $body
     */
    public function authedPut(string $path, array $body): Response
    {
        $response = Http::withToken($this->login())->put($this->baseUrl.$path, $body);

        if ($response->status() === 401) {
            Cache::forget($this->getCacheTokenKey());
            $response = Http::withToken($this->login())->put($this->baseUrl.$path, $body);
        }

        return $response;
    }

    /**
     * Create a new employee in Greatday (POST /Employee accepts an array of employee items).
     * Self-heals a rejected token via authedPost.
     *
     * @param  array<string, mixed>  $employee  One AddEmployeeRequestItem
     *
     * @see docs/greatday-api.json — POST /Employee
     */
    public function addEmployee(array $employee): Response
    {
        return $this->authedPost('/employees/add', [$employee]);
    }

    /**
     * Update an existing employee in Greatday (PUT /Employee accepts an array of transaction items).
     * Self-heals a rejected token via authedPut.
     *
     * @param  array<string, mixed>  $employee  One UpdateEmployeeRequestItem
     *
     * @see docs/greatday-api.json — PUT /Employee
     */
    public function updateEmployee(array $employee): Response
    {
        return $this->authedPut('/Employee', [$employee]);
    }

    /**
     * Push a TERMINATION transaction to Greatday for the given employee number.
     *
     * @see docs/greatday-api.json — PUT /Employee accepts an array of transaction items.
     */
    public function terminateEmployee(string $empNo, string $effectiveDate): Response
    {
        $token = $this->login();

        return Http::withToken($token)
            ->put($this->baseUrl.'/Employee', [
                [
                    'transactionType' => 'TERMINATION',
                    'transactionEffectiveDate' => $effectiveDate,
                    'empNo' => $empNo,
                ],
            ]);
    }

    /**
     * Fetch every Greatday position node across all pages.
     *
     * The whole fetch must succeed before the caller diffs or mutates anything:
     * a partial page set would make legitimate positions look "gone" and risk
     * archiving them, so any page failure aborts with an exception.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException when any page fails to load
     */
    public function getAllPositions(): array
    {
        $page = 1;
        $all = [];

        do {
            $response = $this->authedPost('/company/position', [
                'page' => $page,
                'limit' => 100,
            ]);

            if ($response->failed() || ! isset($response->json()['data'])) {
                logging('error: ', [
                    'context' => 'Greatday position fetch',
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => $response->json() ?? [],
                ]);
                throw new \RuntimeException("Greatday position fetch failed on page {$page}.");
            }

            $json = $response->json();
            $all = array_merge($all, $json['data']);
            $totalPage = (int) ($json['totalPage'] ?? 1);
            $page++;
        } while ($page <= $totalPage);

        return $all;
    }

    /**
     * Check if access token is expired, if expired refresh token.
     */
    protected function checkAccessToken(): void
    {
        $check = Cache::get($this->getCacheTokenKey());

        if ($check) {
            if (now()->greaterThanOrEqualTo($check['token_expires_in'])) {
                // Access token expired, refresh token
                $this->refreshToken($check['refresh_token']);
            }
        } else {
            // No token, request new token
            $this->requestToken();
        }
    }

    /**
     * Refresh token using refresh token and store the new token in cache.
     */
    protected function refreshToken(string $refreshToken): void
    {
        $response = Http::post(
            url: $this->baseUrl.'/auth/refresh',
            data: [
                'refreshToken' => $refreshToken,
            ]
        );

        if ($response->status() === 422) {
            // Refresh token expired, request new token
            $this->requestToken();

            return;
        }

        if ($response->status() < 300 && $this->hasValidToken($response)) {
            // Delete old token
            Cache::forget($this->getCacheTokenKey());

            $this->storeToken($response);
        }
    }

    /**
     * Request new token from Greatday and store it in cache.
     */
    protected function requestToken(): void
    {
        $response = Http::post(
            url: $this->baseUrl.'/auth/login',
            data: [
                'accessKey' => $this->accessKey,
                'accessSecret' => $this->accessSecret,
            ]
        );

        if ($response->status() < 300 && $this->hasValidToken($response)) {
            $this->storeToken($response);
        }
    }

    /**
     * A token response is only usable when it actually carries a non-empty
     * access_token. Caching a response without one is what previously left a
     * broken (truncated) token pinned for the whole 8-day TTL.
     */
    protected function hasValidToken(Response $response): bool
    {
        return ! empty($response->json()['access_token']);
    }

    /**
     * Store the token payload in cache for 1 week (refresh token life time) + 1
     * day (access token life time).
     */
    protected function storeToken(Response $response): void
    {
        Cache::put(
            key: $this->getCacheTokenKey(),
            value: [
                'access_token' => $response->json()['access_token'],
                'refresh_token' => $response->json()['refresh_token'] ?? null,
                'token_expires_in' => now()->addHours(24),
                'refresh_token_expires_in' => now()->addWeek(),
            ],
            ttl: now()->addDays(8)
        );
    }
}
