<?php

use App\Enums\Production\ProjectDealStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use App\Services\DashboardService;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Covers the role branch on DashboardService::getProjectCalendars.
 *
 * Sales users close deals and need to see EVERY project on the calendar
 * (Draft, Temporary, ongoing, interactive) to pick free venue dates -
 * exactly what getProjectCalendarForProspectEvent() returns. Without this
 * branch they fall through to the task-based query, which is scoped to
 * production PICs and returns nothing for a sales staffer.
 */
function ensureCalendarRole(string $name): Role
{
    return Role::firstOrCreate([
        'name' => $name,
        'guard_name' => 'sanctum',
    ]);
}

function actAsRole(string $roleName): User
{
    ensureCalendarRole($roleName);
    $employee = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $user->assignRole($roleName);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

beforeEach(function () {
    $this->service = app(DashboardService::class);
    cache()->flush();
});

it('returns Draft project deals on the calendar for a Sales user', function () {
    // A Draft deal only reaches the calendar via the prospect branch -
    // the task-based branch would never surface it.
    $draft = ProjectDeal::factory()->create([
        'project_date' => '2026-08-15',
        'status' => ProjectDealStatus::Draft->value,
        'name' => 'Prospect Draft Deal',
    ]);

    actAsRole(BaseRole::Sales->value);
    request()->merge(['month' => 8, 'year' => 2026]);

    $result = $this->service->getProjectCalendars();
    // The formatter appends " ($status)" to the name - assert on prefix.
    $names = collect($result['data']['events'])
        ->pluck('customData.name')
        ->all();
    $hasPrefix = fn (string $prefix) => collect($names)
        ->contains(fn ($n) => str_starts_with((string) $n, $prefix));

    expect($result['error'])->toBeFalse()
        ->and($hasPrefix('Prospect Draft Deal'))->toBeTrue();
});

it('returns the same prospect view for Marketing, Finance, Director and Root', function () {
    ProjectDeal::factory()->create([
        'project_date' => '2026-08-15',
        'status' => ProjectDealStatus::Draft->value,
        'name' => 'Shared Draft Deal',
    ]);

    foreach (
        [
            BaseRole::Marketing->value,
            BaseRole::Finance->value,
            BaseRole::Director->value,
            BaseRole::Root->value,
        ] as $roleName
    ) {
        actAsRole($roleName);
        request()->merge(['month' => 8, 'year' => 2026]);

        $result = $this->service->getProjectCalendars();
        $names = collect($result['data']['events'])
            ->pluck('customData.name')
            ->all();
        $hasPrefix = fn (string $prefix) => collect($names)
            ->contains(fn ($n) => str_starts_with((string) $n, $prefix));

        expect($hasPrefix('Shared Draft Deal'))
            ->toBeTrue("role {$roleName} should see the draft");
    }
});

it('excludes deals outside the requested period even for Sales', function () {
    ProjectDeal::factory()->create([
        'project_date' => '2026-08-15',
        'status' => ProjectDealStatus::Draft->value,
        'name' => 'August Deal',
    ]);
    ProjectDeal::factory()->create([
        'project_date' => '2026-07-15',
        'status' => ProjectDealStatus::Draft->value,
        'name' => 'July Deal',
    ]);

    actAsRole(BaseRole::Sales->value);
    request()->merge(['month' => 8, 'year' => 2026]);

    $result = $this->service->getProjectCalendars();
    // The formatter appends " ($status)" to the name - assert on prefix.
    $names = collect($result['data']['events'])
        ->pluck('customData.name')
        ->all();
    $hasPrefix = fn (string $prefix) => collect($names)
        ->contains(fn ($n) => str_starts_with((string) $n, $prefix));

    expect($hasPrefix('August Deal'))->toBeTrue()
        ->and($hasPrefix('July Deal'))->toBeFalse();
});
