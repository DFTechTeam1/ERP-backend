<?php

use Modules\Production\Models\DeadlineChangeReason;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

test('Update data with name is not unique', function () {
    $reason = DeadlineChangeReason::factory()->create();
    $reasonLast = DeadlineChangeReason::factory()->create();

    $response = $this->putJson(
        route('api.production.deadlineReason.update', ['deadlineReason' => $reasonLast->id]),
        [
            'name' => $reason->name,
        ]
    );

    $response->assertStatus(422);

    expect($response->json())->toHaveKeys([
        'message',
        'errors.name',
    ]);
});

test('Update data return success', function () {
    $reason = DeadlineChangeReason::factory()->create();

    $response = $this->putJson(
        route('api.production.deadlineReason.update', ['deadlineReason' => $reason->id]),
        [
            'name' => 'update name',
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('deadline_change_reasons', [
        'name' => 'update name',
        'id' => $reason->id,
    ]);
});
