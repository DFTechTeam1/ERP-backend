<?php

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenService;

use function Pest\Laravel\postJson;

/**
 * POST /api/auth/refresh - rotating, single-use refresh tokens.
 *
 * Each successful refresh issues a brand-new token (same family) and revokes the one presented.
 * The rotated token is returned in the httpOnly refresh cookie; for the After Effects flow
 * (X-device: after-effect) - a non-browser client that authenticates via the body and cannot read
 * the cookie - it is also returned in the response body so the client can carry it forward.
 *
 * Reusing a token that was already rotated is rejected (401). Within a short grace window a reuse
 * is treated as a benign double-refresh and the family survives; after the window it is treated as
 * theft and the whole family is revoked.
 *
 * TokenService::issueAccessToken() is mocked so the suite does not depend on RS256 signing keys;
 * only the refresh rotation is under test here.
 */
const RT_URL = '/api/auth/refresh';
const RT_AE_HEADER = ['X-device' => 'after-effect'];

function rtSvc(): RefreshTokenService
{
    return app(RefreshTokenService::class);
}

/** The stored row for a raw token (looked up by its hash). */
function rtRow(string $raw): ?RefreshToken
{
    return RefreshToken::query()->where('token_hash', rtSvc()->hash($raw))->first();
}

/** Issue a fresh refresh token for a user and return the raw string. */
function rtIssue(User $user, bool $remember = false): string
{
    return rtSvc()->issue($user, $remember)['raw'];
}

beforeEach(function () {
    $this->mock(TokenService::class, function ($mock) {
        $mock->shouldReceive('issueAccessToken')->andReturn('fake.access.token');
    });
});

it('rotates the token and returns the new one in the cookie (not the body) by default', function () {
    $user = User::factory()->create();
    $raw = rtIssue($user);

    $response = postJson(RT_URL, ['refresh_token' => $raw]);

    $response->assertSuccessful();
    expect($response->json('data.access_token'))->toBe('fake.access.token')
        // browser flow: the rotated token lives only in the cookie, never the JSON body
        ->and($response->json('data.refresh_token'))->toBeNull();

    // the presented token is revoked and replaced; the cookie carries a new, active token
    expect(rtRow($raw)->revoked_at)->not->toBeNull();

    // the refresh cookie is not encrypted (API routes skip EncryptCookies), so read it raw
    $cookieRaw = $response->getCookie((string) config('jwt.refresh_cookie'), false)?->getValue();
    expect($cookieRaw)->not->toBeNull()
        ->and($cookieRaw)->not->toBe($raw)
        ->and(rtRow($cookieRaw)->isActive())->toBeTrue();
});

it('also returns the rotated token in the body for the after-effect flow', function () {
    $user = User::factory()->create();
    $raw = rtIssue($user);

    $response = postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER);

    $response->assertSuccessful();

    $newRaw = $response->json('data.refresh_token');
    expect($newRaw)->not->toBeNull()
        ->and($newRaw)->not->toBe($raw)                 // genuinely rotated
        ->and(rtRow($newRaw)->isActive())->toBeTrue()   // the body token is the live one
        ->and(rtRow($raw)->revoked_at)->not->toBeNull(); // the presented token is now revoked

    // same family (rotation, not a brand-new login)
    expect(rtRow($newRaw)->family_id)->toBe(rtRow($raw)->family_id);
});

it('lets the after-effect client keep refreshing with the token from the previous body', function () {
    $user = User::factory()->create();
    $raw = rtIssue($user);

    $first = postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER);
    $second = postJson(RT_URL, ['refresh_token' => $first->json('data.refresh_token')], RT_AE_HEADER);

    $second->assertSuccessful();
    expect($second->json('data.refresh_token'))->not->toBeNull()
        ->and($second->json('data.refresh_token'))->not->toBe($first->json('data.refresh_token'));
});

it('rejects reusing a token that was already rotated', function () {
    $user = User::factory()->create();
    $raw = rtIssue($user);

    postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER)->assertSuccessful();

    // same (now-revoked) token again -> "Refresh token already used" -> 401
    postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER)
        ->assertUnauthorized();
});

it('rejects a request with no token', function () {
    postJson(RT_URL, [])->assertUnauthorized();
});

it('rejects an unknown token', function () {
    postJson(RT_URL, ['refresh_token' => 'not-a-real-token'], RT_AE_HEADER)
        ->assertUnauthorized();
});

it('preserves the family when a rotated token is replayed within the grace window', function () {
    config(['jwt.refresh_grace_seconds' => 10]);
    $user = User::factory()->create();
    $raw = rtIssue($user);

    $newRaw = postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER)->json('data.refresh_token');

    // immediate replay of the old token: rejected, but a benign race -> family left intact
    postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER)->assertUnauthorized();

    expect(rtRow($newRaw)->isActive())->toBeTrue();
    // and the live token can still be rotated
    postJson(RT_URL, ['refresh_token' => $newRaw], RT_AE_HEADER)->assertSuccessful();
});

it('revokes the whole family when a rotated token is replayed after the grace window (theft)', function () {
    config(['jwt.refresh_grace_seconds' => 10]);
    $user = User::factory()->create();
    $raw = rtIssue($user);

    $newRaw = postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER)->json('data.refresh_token');

    // replay the old token well after the grace window -> treated as theft
    $this->travel(11)->seconds();
    postJson(RT_URL, ['refresh_token' => $raw], RT_AE_HEADER)->assertUnauthorized();

    // the whole family is burned: even the previously-live token no longer works
    expect(rtRow($newRaw)->revoked_at)->not->toBeNull();
    postJson(RT_URL, ['refresh_token' => $newRaw], RT_AE_HEADER)->assertUnauthorized();
});
