<?php

use App\Models\User;

/**
 * Feature tests run against the test DB (erp_testing_new) and are wrapped in a
 * DatabaseTransactions transaction that rolls back automatically — so anything
 * written here is gone after the test, and the schema is NEVER re-migrated.
 *
 * Prerequisite (one-time, and after any new migration): `make test-migrate`.
 */
it('persists within a transaction that rolls back after the test', function () {
    $user = User::factory()->create();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});
