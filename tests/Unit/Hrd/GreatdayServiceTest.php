<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Services\GreatdayService;

beforeEach(function () {
    Cache::forget('greatday_token');

    config([
        'app.greatday.base_url' => 'https://greatday.test/api',
        'app.greatday.access_key' => 'key',
        'app.greatday.access_secret' => 'secret',
    ]);
});

it('terminateEmployee self-heals on a 401 by re-authenticating and retrying once', function () {
    Http::fake([
        'greatday.test/api/auth/login' => Http::sequence()
            ->push(['access_token' => 'stale-token', 'refresh_token' => 'ref-1'], 200)
            ->push(['access_token' => 'fresh-token', 'refresh_token' => 'ref-2'], 200),
        'greatday.test/api/Employee' => Http::sequence()
            ->push(['message' => 'unauthorized'], 401)
            ->push([['success' => true]], 200),
    ]);

    $response = (new GreatdayService)->terminateEmployee('DF999', '2026-07-25');

    expect($response->status())->toBe(200)
        ->and($response->json())->toBe([['success' => true]]);

    // logged in twice: once with the stale token, once after forgetting it on the 401
    Http::assertSentCount(4);

    // the retried PUT carried the freshly issued token
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Employee')
            && $request->header('Authorization')[0] === 'Bearer fresh-token';
    });
});

it('terminateEmployee does not retry when the first call succeeds', function () {
    Http::fake([
        'greatday.test/api/auth/login' => Http::response([
            'access_token' => 'good-token',
            'refresh_token' => 'ref',
        ], 200),
        'greatday.test/api/Employee' => Http::response([['success' => true]], 200),
    ]);

    $response = (new GreatdayService)->terminateEmployee('DF999', '2026-07-25');

    expect($response->status())->toBe(200);

    // one login + one PUT, no re-auth
    Http::assertSentCount(2);
});
