<?php

namespace Modules\Hrd\Services;

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
     *
     * @return string
     */
    public function login(): string
    {
        $check = \Illuminate\Support\Facades\Cache::get($this->getCacheTokenKey());

        if (! $check) {
            // No token, request new token
            $this->requestToken();
        }

        // Check if access token is expired, if expired refresh token
        $this->checkAccessToken();

        return \Illuminate\Support\Facades\Cache::get($this->getCacheTokenKey())['access_token'] ?? '';
    }

    /**
     * Check if access token is expired, if expired refresh token.
     *
     * @return void
     */
    protected function checkAccessToken(): void
    {
        $check = \Illuminate\Support\Facades\Cache::get($this->getCacheTokenKey());

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
     *
     * @param string $refreshToken
     * @return void
     */
    protected function refreshToken(string $refreshToken): void
    {
        $response = \Illuminate\Support\Facades\Http::post(
            url: $this->baseUrl . '/auth/refresh',
            data: [
                'refreshToken' => $refreshToken,
            ]
        );

        if ($response->status() < 300) {
            // Delete old token
            \Illuminate\Support\Facades\Cache::forget($this->getCacheTokenKey());

            // Store to cache for 1 week (refresh token life time) + 1 day (access token life time)
            \Illuminate\Support\Facades\Cache::put(
                key: $this->getCacheTokenKey(),
                value: [
                    'access_token' => $response->json()['access_token'],
                    'refresh_token' => $response->json()['refresh_token'],
                    'token_expires_in' => now()->addHours(24),
                    'refresh_token_expires_in' => now()->addWeek(),
                ],
                ttl: now()->addDays(8)
            );
        }
    }

    /**
     * Request new token from Greatday and store it in cache.
     *
     * @return void
     */
    protected function requestToken(): void
    {
        $response = \Illuminate\Support\Facades\Http::post(
            url: $this->baseUrl . '/auth/login',
            data: [
                'accessKey' => $this->accessKey,
                'accessSecret' => $this->accessSecret,
            ]
        );

        if ($response->status() < 300) {
            // Store to cache for 1 week (refresh token life time) + 1 day (access token life time)
            \Illuminate\Support\Facades\Cache::put(
                key: $this->getCacheTokenKey(),
                value: [
                    'access_token' => $response->json()['access_token'],
                    'refresh_token' => $response->json()['refresh_token'],
                    'token_expires_in' => now()->addHours(24),
                    'refresh_token_expires_in' => now()->addWeek(),
                ],
                ttl: now()->addDays(8)
            );
        }
    }
}