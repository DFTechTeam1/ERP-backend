<?php

use Modules\Production\Models\DeadlineChangeReason;

use function Pest\Laravel\{deleteJson, withHeaders, actingAs};

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

test('Delete data return success', function () {
    $reason = DeadlineChangeReason::factory()->create();

    $response = $this->deleteJson(route('api.production.deadlineReason.destroy', ['deadlineReason' => $reason->id]));

    $response->assertStatus(201);

    $this->assertDatabaseCount('deadline_change_reasons', 1);
    $this->assertDatabaseMissing('deadline_change_reasons', [
        'id' => $reason->id,
        'deleted_at' => null
    ]);
});
