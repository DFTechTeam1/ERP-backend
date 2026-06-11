<?php

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TokenService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Generate a throwaway RSA keypair and point the jwt config at it so the issuer
 * and the verifying middleware share the same key during the test run.
 */
beforeEach(function () {
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($resource, $privatePem);
    $publicPem = openssl_pkey_get_details($resource)['key'];

    config()->set('jwt.private_key', $privatePem);
    config()->set('jwt.public_key', $publicPem);
    config()->set('jwt.issuer', 'https://auth.test');
    config()->set('jwt.audience', 'erp');
    config()->set('jwt.kid', 'key-1');
    config()->set('jwt.access_ttl', 30);
    config()->set('jwt.refresh_ttl', 1);
    config()->set('jwt.refresh_ttl_remember', 30);
    config()->set('jwt.refresh_grace_seconds', 10);
    config()->set('jwt.refresh_cookie', 'df_refresh_token');
    config()->set('jwt.refresh_cookie_secure', false);
});

/**
 * @param  array<int, string>  $permissions
 */
function makeAuthUser(array $permissions = ['deal.create', 'lead.read'], string $roleName = 'tester'): User
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);

    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']));
    }

    $user = User::factory()->create(['user_status' => 1]);
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

/**
 * @return array{0: array<string, mixed>, 1: array<string, mixed>} [header, claims]
 */
function decodeJwt(string $jwt): array
{
    [$header, $payload] = explode('.', $jwt);

    return [
        json_decode(base64_decode(strtr($header, '-_', '+/')), true),
        json_decode(base64_decode(strtr($payload, '-_', '+/')), true),
    ];
}

it('issues an RS256 access token with the expected header and claims', function () {
    $user = makeAuthUser(['deal.create', 'lead.read']);

    $jwt = app(TokenService::class)->issueAccessToken($user);

    [$header, $claims] = decodeJwt($jwt);

    expect($header['alg'])->toBe('RS256');
    expect($header['kid'])->toBe('key-1');
    expect($claims['iss'])->toBe('https://auth.test');
    expect(Arr::wrap($claims['aud']))->toContain('erp');
    expect($claims['sub'])->toBe((string) $user->id);
    expect($claims)->toHaveKey('jti');
    expect($claims['permissions'])->toContain('deal.create')->toContain('lead.read');
    expect($claims['roles'])->toContain('tester');
    expect($claims['exp'] - $claims['iat'])->toBe(30 * 60);
});

it('does not embed the menu in the access token', function () {
    $jwt = app(TokenService::class)->issueAccessToken(makeAuthUser());

    [, $claims] = decodeJwt($jwt);

    expect($claims)->not->toHaveKey('menus');
    expect($claims)->not->toHaveKey('menu');
});

it('serves the menu for a valid access token and 401 for missing/tampered tokens', function () {
    $user = makeAuthUser(['deal.create'], 'root');
    $jwt = app(TokenService::class)->issueAccessToken($user);

    $this->getJson('/api/menu')->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer '.$jwt.'tampered')
        ->getJson('/api/menu')
        ->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer '.$jwt)
        ->getJson('/api/menu')
        ->assertOk()
        ->assertJsonStructure(['data' => ['menus']]);
});

it('rejects a token whose payload was tampered (signature mismatch)', function () {
    $user = makeAuthUser();
    $jwt = app(TokenService::class)->issueAccessToken($user);

    [$header, $payload, $signature] = explode('.', $jwt);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    $claims['permissions'][] = 'finance.admin';
    $forgedPayload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
    $forged = "{$header}.{$forgedPayload}.{$signature}";

    $this->withHeader('Authorization', 'Bearer '.$forged)
        ->getJson('/api/menu')
        ->assertUnauthorized();
});

it('rotates the refresh token and issues a new access token', function () {
    $user = makeAuthUser();
    $issued = app(RefreshTokenService::class)->issue($user, false);

    $this->withCookie('df_refresh_token', $issued['raw'])
        ->postJson('/api/auth/refresh')
        ->assertOk()
        ->assertJsonStructure(['data' => ['access_token']])
        ->assertCookie('df_refresh_token');

    expect(RefreshToken::find($issued['model']->id)->revoked_at)->not->toBeNull();
});

it('returns 401 when refreshing with an unknown token', function () {
    $this->withCookie('df_refresh_token', 'not-a-real-token')
        ->postJson('/api/auth/refresh')
        ->assertUnauthorized();
});

it('revokes the whole family when a revoked refresh token is replayed (theft)', function () {
    $user = makeAuthUser();
    $service = app(RefreshTokenService::class);

    $first = $service->issue($user, false);
    $second = $service->rotate($first['raw']);

    // Past the grace window: replaying the revoked first token is theft.
    $this->travel(11)->seconds();

    $this->withCookie('df_refresh_token', $first['raw'])
        ->postJson('/api/auth/refresh')
        ->assertUnauthorized();

    expect(RefreshToken::find($second['model']->id)->revoked_at)->not->toBeNull();
});

it('does not trigger theft on a rapid double refresh inside the grace window', function () {
    $user = makeAuthUser();
    $service = app(RefreshTokenService::class);

    $first = $service->issue($user, false);
    $second = $service->rotate($first['raw']);

    // Immediate replay (within grace) — losing request fails but family survives.
    $this->withCookie('df_refresh_token', $first['raw'])
        ->postJson('/api/auth/refresh')
        ->assertUnauthorized();

    expect(RefreshToken::find($second['model']->id)->revoked_at)->toBeNull();
});

it('uses a 30 day expiry with remember and ~1 day without', function () {
    $service = app(RefreshTokenService::class);
    $user = makeAuthUser();

    $withRemember = $service->issue($user, true);
    $withoutRemember = $service->issue($user, false);

    expect($withRemember['model']->expires_at->gt(now()->addDays(29)))->toBeTrue();
    expect($withRemember['model']->expires_at->lte(now()->addDays(30)->addMinute()))->toBeTrue();

    expect($withoutRemember['model']->expires_at->gt(now()->addHours(23)))->toBeTrue();
    expect($withoutRemember['model']->expires_at->lte(now()->addDays(1)->addMinute()))->toBeTrue();
});

it('slides the expiry forward on rotation and carries the remember flag', function () {
    $service = app(RefreshTokenService::class);
    $user = makeAuthUser();

    $first = $service->issue($user, true);

    $this->travel(2)->days();
    $second = $service->rotate($first['raw']);

    expect($second['model']->remember)->toBeTrue();
    expect($second['model']->family_id)->toBe($first['model']->family_id);
    expect($second['model']->expires_at->gt(now()->addDays(29)))->toBeTrue();
});

it('login issues both tokens and a refresh cookie (dual-issue, remember = 30 days)', function () {
    config()->set('app.express_endpoint', 'http://express.test');
    config()->set('app.python_endpoint', 'http://python.test');

    Http::fake([
        '*hrd/auth/login' => Http::response(['data' => ['token' => 'express-token']], 200),
        '*auth/access-token' => Http::response(['data' => ['access_token' => 'report-token']], 200),
    ]);

    $user = makeAuthUser(['deal.create'], 'root');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember_me' => true,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['access_token', 'main', 'expressToken', 'reportingToken']])
        ->assertCookie('df_refresh_token');

    $refresh = RefreshToken::where('user_id', $user->id)->first();
    expect($refresh)->not->toBeNull();
    expect($refresh->remember)->toBeTrue();
    expect($refresh->expires_at->gt(now()->addDays(29)))->toBeTrue();

    [, $claims] = decodeJwt($response->json('data.access_token'));
    expect($claims['sub'])->toBe((string) $user->id);
});
