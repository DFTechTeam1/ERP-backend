<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the dedicated test database (erp_testing_new).
| We use DatabaseTransactions — NOT RefreshDatabase — so the schema is
| migrated ONCE (via `make test-migrate`) and never re-migrated. Each test
| is wrapped in a transaction that is rolled back when it finishes, leaving
| the schema intact for the next test. With 400+ migrations this is the
| difference between seconds and minutes per run.
|
| Workflow:
|   - First time, or after adding/changing a migration:  make test-migrate
|   - Every test run after that:                          make test
|
| Unit tests get no database and no transaction — keep them pure (no DB,
| no container) so they stay instant.
|
*/

pest()
    ->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in('Feature');

pest()
    ->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Add custom expectations here, e.g.:
|   expect()->extend('toBeOne', fn () => $this->toBe(1));
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Expose project-specific helpers here as global functions to reduce
| boilerplate across test files, e.g. an authenticated-actor helper:
|
|   function actingAsUser(?\App\Models\User $user = null): \App\Models\User
|   {
|       $user ??= \App\Models\User::factory()->create();
|       test()->actingAs($user);
|
|       return $user;
|   }
|
*/
