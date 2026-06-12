<?php

use App\Models\User;
use App\Services\Auth\TokenService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Centralized-auth Pass 2: the `auth.session` guard must accept EITHER the new
 * RS256 access token (verified locally) OR a legacy Sanctum token, so routes
 * can migrate without a flag-day cutover. These tests cover both paths plus the
 * rejection cases against a real `auth.session`-protected route (GET /api/user).
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
});

function v2MakeUser(array $permissions = ['deal.create'], string $roleName = 'tester'): User
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

it('rejects an auth.session route when no token is present', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('authenticates an auth.session route with a valid RS256 access token', function () {
    $user = v2MakeUser();
    $jwt = app(TokenService::class)->issueAccessToken($user);

    $this->withHeader('Authorization', 'Bearer '.$jwt)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonFragment(['id' => $user->id]);
});

it('rejects a tampered RS256 token on an auth.session route', function () {
    $user = v2MakeUser();
    $jwt = app(TokenService::class)->issueAccessToken($user);

    $this->withHeader('Authorization', 'Bearer '.$jwt.'tampered')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('still authenticates an auth.session route with a legacy Sanctum token (fallback)', function () {
    $user = v2MakeUser();
    $sanctum = $user->createToken('test', ['*'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$sanctum)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonFragment(['id' => $user->id]);
});
