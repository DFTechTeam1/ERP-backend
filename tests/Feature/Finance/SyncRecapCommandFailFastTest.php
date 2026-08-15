<?php

/**
 * Fail-fast contract: the command MUST refuse to run before touching any
 * DB rows when the created_by target user does not exist. This regression
 * exists because the first apply-run blew up mid-loop with an FK/NOT NULL
 * violation on project_deal_refunds.created_by, which forced a full DB
 * transaction rollback of every prior insert.
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('fails when --as-user points to a non-existent id, before starting the sync', function () {
    // Sanity: pick an id that definitely does not exist.
    $missingId = ((int) DB::table('users')->max('id')) + 1_000_000;

    $this->artisan('finance:sync-recap', [
        '--as-user' => (string) $missingId,
        '--dry-run' => true,
        '--auto-apply' => true,
        '--no-interaction' => true,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain("--as-user={$missingId} does not exist in users table");
});

it('resolves the given --as-user id when it exists', function () {
    $user = User::query()->first() ?? User::factory()->create();

    // Point at a file we know is missing so the command exits AFTER user
    // resolution but BEFORE the loop — the "Attributing inserts" line is
    // what we care about.
    $this->artisan('finance:sync-recap', [
        'path' => '/nonexistent/path/recap.xlsx',
        '--as-user' => (string) $user->id,
        '--dry-run' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain("Attributing inserts to users.id = {$user->id}")
        ->assertExitCode(1);
});
