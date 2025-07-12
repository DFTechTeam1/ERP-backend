<?php

use Modules\Production\Models\DeadlineChangeReason;

use function Pest\Laravel\{getJson, postJson, withHeaders, actingAs};

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

test('Get list of reason return success', function () {
    DeadlineChangeReason::factory()->count(20)->create();

    $response = $this->getJson(route('api.production.deadlineReason.index'));

    $response->assertStatus(201);

    expect($response->json())->toHaveKeys([
        'message',
        'data.paginated',
        'data.totalData'
    ]);

    expect($response->json()['data']['totalData'])->toBe(20);
    expect(count($response->json()['data']['paginated']))->toBe(20);
});

test("List of reason with pagination parameter", function () {
    DeadlineChangeReason::factory()->count(20)->create();

    $response = $this->getJson(route('api.production.deadlineReason.index') . "?itemsPerPage=10&page=1");

    $response->assertStatus(201);

    expect($response->json())->toHaveKeys([
        'message',
        'data.paginated',
        'data.totalData'
    ]);

    expect($response->json()['data']['totalData'])->toBe(20);
    expect(count($response->json()['data']['paginated']))->toBe(10);
});