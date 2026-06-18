<?php

use App\Enums\System\BaseRole;

it('forbids users without an allowed role from viewing authentication logs', function () {
    $user = initAuthenticateUser(roleName: BaseRole::Marketing->value);

    $this->actingAs($user);

    $this->getJson('/api/authentication-logs')->assertStatus(403);
});

it('lists authentication logs for an allowed role', function () {
    $user = initAuthenticateUser(roleName: BaseRole::ItSupport->value);

    $this->actingAs($user);

    $user->authentications()->create([
        'ip_address' => '203.0.113.10',
        'user_agent' => 'PHPUnit',
        'login_at' => now(),
        'login_successful' => true,
    ]);

    $response = $this->getJson('/api/authentication-logs');

    $response->assertStatus(200);
    $response->assertJsonPath('data.totalData', 1);
    $response->assertJsonFragment([
        'ip_address' => '203.0.113.10',
        'user_email' => $user->email,
        'login_successful' => true,
    ]);
});

it('returns authentication log summary counts for an allowed role', function () {
    $user = initAuthenticateUser(roleName: BaseRole::Director->value);

    $this->actingAs($user);

    $user->authentications()->createMany([
        ['ip_address' => '203.0.113.10', 'login_at' => now(), 'login_successful' => true],
        ['ip_address' => '203.0.113.11', 'login_at' => now(), 'login_successful' => false],
    ]);

    $response = $this->getJson('/api/authentication-logs/summary');

    $response->assertStatus(200);
    $response->assertJsonPath('data.total', 2);
    $response->assertJsonPath('data.successful', 1);
    $response->assertJsonPath('data.failed', 1);
});

it('forbids the summary endpoint for a disallowed role', function () {
    $user = initAuthenticateUser(roleName: BaseRole::Marketing->value);

    $this->actingAs($user);

    $this->getJson('/api/authentication-logs/summary')->assertStatus(403);
});
