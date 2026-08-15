<?php

use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectLeadStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Finance\Services\MarketingDashboardService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\ProjectLead;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Coverage for role-scoped access on the sales dashboard endpoints.
 *
 * Rules under test:
 *   - Sales user sees only their own deals/leads (scope_deal_ids = own).
 *   - Director/Root sees company-wide by default.
 *   - Director/Root + ?sales_employee_id narrows to that salesperson.
 *   - Sales user cannot escape their scope even by sending sales_employee_id.
 *   - Non-authorized roles are forbidden.
 *   - getSalesPeople is executive-only.
 */

/** Create the Spatie role if it does not yet exist. */
function ensureRole(string $name): Role
{
    return Role::firstOrCreate([
        'name' => $name,
        'guard_name' => 'sanctum',
    ]);
}

/** Return a user with the given role attached to a fresh employee. */
function userWithRole(string $roleName): User
{
    ensureRole($roleName);

    $employee = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $user->assignRole($roleName);

    // Spatie caches permission/role lookups across the request lifecycle -
    // clear so hasRole() sees the freshly assigned role in this test.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * Create a Final deal for the given month/year and attach the given
 * employee as its marketing owner.
 */
function dealForMarketing(
    Employee $employee,
    int $year,
    int $month,
    bool $isFullyPaid = false,
    ProjectDealStatus $status = ProjectDealStatus::Final,
): ProjectDeal {
    $date = Carbon\Carbon::create($year, $month, 15)->toDateString();

    $deal = ProjectDeal::factory()->create([
        'project_date' => $date,
        'status' => $status->value,
        'is_fully_paid' => $isFullyPaid,
    ]);

    ProjectDealMarketing::create([
        'project_deal_id' => $deal->id,
        'employee_id' => $employee->id,
    ]);

    return $deal;
}

beforeEach(function () {
    $this->service = app(MarketingDashboardService::class);

    // Pre-create every role we exercise so hasRole() has something to match.
    ensureRole(BaseRole::Sales->value);
    ensureRole(BaseRole::Director->value);
    ensureRole(BaseRole::Root->value);
    ensureRole(BaseRole::Finance->value);
    ensureRole(BaseRole::Production->value);

    // Isolate cache-based deal-identifier state - the ProjectDeal `creating`
    // hook stamps identifier_number via a cache-backed counter. Clearing it
    // avoids collisions from residual state in longer test files.
    cache()->flush();
});

it('rejects an unauthenticated caller with 403', function () {
    $result = $this->service->getPipeline(month: 6, year: 2026);

    expect($result)
        ->toHaveKey('code', 403)
        ->and($result['error'])->toBeTrue();
});

it('rejects an authorised user in a non-sales role with 403', function () {
    $user = userWithRole(BaseRole::Finance->value);
    actingAs($user);

    $result = $this->service->getPipeline(month: 6, year: 2026);

    expect($result)->toHaveKey('code', 403);
});

it('scopes the pipeline to the sales user own deals', function () {
    $me = Employee::factory()->create();
    $stranger = Employee::factory()->create();

    // Two of mine (one Final, one fully-paid Final) and one for a stranger.
    dealForMarketing($me, 2026, 6);
    dealForMarketing($me, 2026, 6, isFullyPaid: true);
    dealForMarketing($stranger, 2026, 6);

    $user = User::factory()->create(['employee_id' => $me->id]);
    ensureRole(BaseRole::Sales->value);
    $user->assignRole(BaseRole::Sales->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    $result = $this->service->getPipeline(month: 6, year: 2026);
    $stages = collect($result['data']['stages'])->keyBy('key');

    expect($result['code'])->toBe(201)
        ->and($stages['deals']['count'])->toBe(2)
        ->and($stages['finalized']['count'])->toBe(2)
        ->and($stages['fullyPaid']['count'])->toBe(1)
        ->and($result['data']['scope']['is_filtered'])->toBeTrue()
        ->and($result['data']['scope']['employee_id'])->toBe($me->id);
});

it('returns company-wide pipeline for an executive without a filter', function () {
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();
    dealForMarketing($a, 2026, 6);
    dealForMarketing($b, 2026, 6);
    dealForMarketing($b, 2026, 6, isFullyPaid: true);

    $director = userWithRole(BaseRole::Director->value);
    actingAs($director);

    $result = $this->service->getPipeline(month: 6, year: 2026);
    $stages = collect($result['data']['stages'])->keyBy('key');

    expect($stages['deals']['count'])->toBe(3)
        ->and($stages['finalized']['count'])->toBe(3)
        ->and($stages['fullyPaid']['count'])->toBe(1)
        ->and($result['data']['scope']['is_filtered'])->toBeFalse()
        ->and($result['data']['scope']['employee_id'])->toBeNull();
});

it('narrows the pipeline to the requested sales person for an executive', function () {
    $a = Employee::factory()->create();
    $b = Employee::factory()->create();
    dealForMarketing($a, 2026, 6);
    dealForMarketing($b, 2026, 6);
    dealForMarketing($b, 2026, 6, isFullyPaid: true);

    $director = userWithRole(BaseRole::Director->value);
    actingAs($director);

    $result = $this->service->getPipeline(
        month: 6,
        year: 2026,
        salesEmployeeId: $b->id,
    );
    $stages = collect($result['data']['stages'])->keyBy('key');

    expect($stages['deals']['count'])->toBe(2)
        ->and($stages['fullyPaid']['count'])->toBe(1)
        ->and($result['data']['scope']['is_filtered'])->toBeTrue()
        ->and($result['data']['scope']['employee_id'])->toBe($b->id);
});

it('ignores the sales_employee_id filter for a sales user (cannot escape own scope)', function () {
    $me = Employee::factory()->create();
    $stranger = Employee::factory()->create();
    dealForMarketing($me, 2026, 6);
    dealForMarketing($stranger, 2026, 6);
    dealForMarketing($stranger, 2026, 6);

    $user = User::factory()->create(['employee_id' => $me->id]);
    $user->assignRole(BaseRole::Sales->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    // Sales user tries to peek at the stranger's data.
    $result = $this->service->getPipeline(
        month: 6,
        year: 2026,
        salesEmployeeId: $stranger->id,
    );
    $stages = collect($result['data']['stages'])->keyBy('key');

    // Scope is still theirs, not the requested id.
    expect($stages['deals']['count'])->toBe(1)
        ->and($result['data']['scope']['employee_id'])->toBe($me->id);
});

it('scopes the my-deals table to the sales user own deals', function () {
    $me = Employee::factory()->create();
    $stranger = Employee::factory()->create();
    dealForMarketing($me, 2026, 6);
    dealForMarketing($me, 2026, 6);
    dealForMarketing($stranger, 2026, 6);

    $user = User::factory()->create(['employee_id' => $me->id]);
    $user->assignRole(BaseRole::Sales->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    $result = $this->service->getMyDeals(month: 6, year: 2026);

    expect($result['data']['total'])->toBe(2)
        ->and($result['data']['items'])->toHaveCount(2);
});

it('excludes cancelled deals from the pipeline and my-deals', function () {
    $me = Employee::factory()->create();
    dealForMarketing($me, 2026, 6);
    dealForMarketing($me, 2026, 6, status: ProjectDealStatus::Canceled);

    $user = User::factory()->create(['employee_id' => $me->id]);
    $user->assignRole(BaseRole::Sales->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    $pipeline = $this->service->getPipeline(month: 6, year: 2026);
    $deals = $this->service->getMyDeals(month: 6, year: 2026);

    $stages = collect($pipeline['data']['stages'])->keyBy('key');

    expect($stages['deals']['count'])->toBe(1)
        ->and($deals['data']['total'])->toBe(1);
});

it('excludes deals outside the requested period', function () {
    $me = Employee::factory()->create();
    dealForMarketing($me, 2026, 6);
    dealForMarketing($me, 2026, 7);

    $user = User::factory()->create(['employee_id' => $me->id]);
    $user->assignRole(BaseRole::Sales->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    $result = $this->service->getPipeline(month: 6, year: 2026);
    $stages = collect($result['data']['stages'])->keyBy('key');

    expect($stages['deals']['count'])->toBe(1);
});

it('counts leads via pic_id JSON membership and created_by', function () {
    $me = Employee::factory()->create();
    $other = Employee::factory()->create();

    // Assigned via pic_id array.
    ProjectLead::factory()->create([
        'project_date' => '2026-06-10',
        'pic_id' => [$me->id, $other->id],
        'status' => ProjectLeadStatus::ACTIVE->value,
    ]);
    // Assigned via created_by only.
    ProjectLead::factory()->create([
        'project_date' => '2026-06-20',
        'pic_id' => [$other->id],
        'created_by' => $me->id,
        'status' => ProjectLeadStatus::ACTIVE->value,
    ]);
    // Unrelated - should NOT count.
    ProjectLead::factory()->create([
        'project_date' => '2026-06-25',
        'pic_id' => [$other->id],
        'created_by' => $other->id,
        'status' => ProjectLeadStatus::ACTIVE->value,
    ]);
    // Cancelled - should NOT count.
    ProjectLead::factory()->create([
        'project_date' => '2026-06-27',
        'pic_id' => [$me->id],
        'status' => ProjectLeadStatus::CANCELLED->value,
    ]);

    $user = User::factory()->create(['employee_id' => $me->id]);
    $user->assignRole(BaseRole::Sales->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    $result = $this->service->getPipeline(month: 6, year: 2026);
    $stages = collect($result['data']['stages'])->keyBy('key');

    expect($stages['leads']['count'])->toBe(2);
});

it('exposes the distinct sales people list to executives', function () {
    $a = Employee::factory()->create(['name' => 'Alice']);
    $b = Employee::factory()->create(['name' => 'Bob']);
    $c = Employee::factory()->create(['name' => 'Charlie']);

    // A and B are marketings on at least one deal; C has none.
    dealForMarketing($a, 2026, 6);
    dealForMarketing($a, 2026, 7);
    dealForMarketing($b, 2026, 6);

    $director = userWithRole(BaseRole::Director->value);
    actingAs($director);

    $result = $this->service->getSalesPeople();
    $ids = collect($result['data'])->pluck('id')->all();

    expect($result['code'])->toBe(201)
        ->and($ids)->toContain($a->id, $b->id)
        ->and($ids)->not->toContain($c->id);
});

it('excludes the authenticated user from the sales people list', function () {
    // The executive viewing the dashboard is also on the marketings table
    // (e.g. self-assigned to a deal). They should not see themselves as a
    // filter option.
    $me = Employee::factory()->create(['name' => 'Ilham']);
    $peer = Employee::factory()->create(['name' => 'Sharon']);
    dealForMarketing($me, 2026, 6);
    dealForMarketing($peer, 2026, 6);

    ensureRole(BaseRole::Director->value);
    $director = User::factory()->create(['employee_id' => $me->id]);
    $director->assignRole(BaseRole::Director->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($director);

    $result = $this->service->getSalesPeople();
    $ids = collect($result['data'])->pluck('id')->all();

    expect($ids)
        ->toContain($peer->id)
        ->and($ids)->not->toContain($me->id);
});

it('excludes employees whose user carries the Root role', function () {
    $rootEmployee = Employee::factory()->create(['name' => 'Root Admin']);
    $salesEmployee = Employee::factory()->create(['name' => 'Grace']);

    // Both are on the marketings table, but the root user should be filtered.
    dealForMarketing($rootEmployee, 2026, 6);
    dealForMarketing($salesEmployee, 2026, 6);

    ensureRole(BaseRole::Root->value);
    $rootUser = User::factory()->create(['employee_id' => $rootEmployee->id]);
    $rootUser->assignRole(BaseRole::Root->value);
    // The Employee model reads `user` via `user_id`, so mirror the FK back.
    $rootEmployee->update(['user_id' => $rootUser->id]);

    // Sales user linked so they clearly are not filtered by their role.
    ensureRole(BaseRole::Sales->value);
    $salesUser = User::factory()->create(['employee_id' => $salesEmployee->id]);
    $salesUser->assignRole(BaseRole::Sales->value);
    $salesEmployee->update(['user_id' => $salesUser->id]);

    // Fresh director as the caller (separate employee, no filter impact).
    $director = userWithRole(BaseRole::Director->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($director);

    $result = $this->service->getSalesPeople();
    $ids = collect($result['data'])->pluck('id')->all();

    expect($ids)
        ->toContain($salesEmployee->id)
        ->and($ids)->not->toContain($rootEmployee->id);
});

it('forbids a sales user from listing the sales people directory', function () {
    $user = userWithRole(BaseRole::Sales->value);
    actingAs($user);

    $result = $this->service->getSalesPeople();

    expect($result)->toHaveKey('code', 403);
});
