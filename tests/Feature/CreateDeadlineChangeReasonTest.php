<?php

use Modules\Production\Models\DeadlineChangeReason;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

test('Create reason with missing parameter', function () {
    $payload = [];

    $response = $this->postJson(route('api.production.deadlineReason.store'), $payload);

    $response->assertStatus(422);

    expect($response->json())->toHaveKeys([
        'message',
        'errors.name',
    ]);
});

test('Create reason with same name', function () {
    $reason = DeadlineChangeReason::factory()->create();

    $response = $this->postJson(route('api.production.deadlineReason.store'), [
        'name' => $reason->name,
    ]);

    $response->assertStatus(422);

    expect($response->json())->toHaveKeys([
        'message',
        'errors.name',
    ]);
});

test('Create reason return success', function () {
    $response = $this->postJson(route('api.production.deadlineReason.store'), [
        'name' => 'name',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseCount('deadline_change_reasons', 1);
    $this->assertDatabaseHas('deadline_change_reasons', [
        'name' => 'name',
    ]);
});