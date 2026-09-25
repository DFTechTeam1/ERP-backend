<?php

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * DFEngine list endpoints on ProjectService / ProjectController:
 *   - listProjectDFEngine() : GET /api/production/dfengine/projects
 *   - listTaskDFEngine()    : GET /api/production/dfengine/projects/{projectUid}
 *
 * listProjectDFEngine returns only active projects (OnGoing / PartialComplete), scoped by the
 * caller's role, and each row's action flags come from the caller's dfengine_access /
 * dfengine_edit_setting permissions. listTaskDFEngine lists a project's tasks, scoping a production
 * caller to the tasks they are a PIC of. getUser() reads the acting user's first role, so every
 * actor needs a role; the two dfengine permissions must exist (hasPermissionTo throws otherwise),
 * so the helper always seeds them.
 */
function dfengineService(): ProjectService
{
    return app(ProjectService::class);
}

/** The two DFEngine permissions must exist so hasPermissionTo() resolves instead of throwing. */
function ensureDfenginePermissions(): void
{
    Permission::firstOrCreate(['name' => 'dfengine_access', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'dfengine_edit_setting', 'guard_name' => 'sanctum']);
}

/**
 * Authenticate (sanctum) as a fresh user holding $role, optionally with an employee and any of the
 * dfengine permissions granted.
 *
 * @param  array<int, string>  $permissions
 */
function dfengineActingAs(string $role, array $permissions = [], bool $withEmployee = false): User
{
    ensureDfenginePermissions();
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);

    if ($withEmployee) {
        $employee = Employee::factory()->withUser()->create();
        $user = User::where('employee_id', $employee->id)->firstOrFail();
    } else {
        $user = User::factory()->create();
    }

    $user->assignRole($role);
    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user, 'sanctum');

    return $user;
}

// ---- listProjectDFEngine (service) ----------------------------------------

describe('listProjectDFEngine (service)', function () {
    it('lists only active projects with dfengine action flags for a permitted caller', function () {
        dfengineActingAs(BaseRole::Root->value, ['dfengine_access', 'dfengine_edit_setting']);

        $ongoing = Project::factory()->withBoards()->create([
            'name' => 'Ongoing Show',
            'status' => ProjectStatus::OnGoing->value,
        ]);
        // Two real tasks so task_count is asserted (the factory spawns unrelated throwaway
        // projects, so this test matches rows by name rather than by an exact total).
        ProjectTask::factory()->count(2)->create([
            'project_id' => $ongoing->id,
            'project_board_id' => $ongoing->boards->first()->id,
            'status' => TaskStatus::OnProgress->value,
        ]);
        Project::factory()->withBoards()->create([
            'name' => 'Partial Show',
            'status' => ProjectStatus::PartialComplete->value,
        ]);
        // Completed is not active -> excluded.
        Project::factory()->withBoards()->create([
            'name' => 'Done Show',
            'status' => ProjectStatus::Completed->value,
        ]);

        $response = dfengineService()->listProjectDFEngine();

        expect($response['error'])->toBeFalse();

        $names = collect($response['data']['paginated'])->pluck('name');
        expect($names)->toContain('Ongoing Show')
            ->and($names)->toContain('Partial Show')
            ->and($names)->not->toContain('Done Show');

        $ongoingRow = collect($response['data']['paginated'])->firstWhere('name', 'Ongoing Show');
        expect($ongoingRow->task_count)->toBe(2)
            ->and($ongoingRow->classification)->not->toBeEmpty()
            ->and($ongoingRow->action['can_open'])->toBeTrue()
            ->and($ongoingRow->action['can_edit_setting'])->toBeTrue();
    });

    it('reflects the caller dfengine permissions in the action flags', function () {
        // dfengine_access granted, dfengine_edit_setting NOT granted.
        dfengineActingAs(BaseRole::Root->value, ['dfengine_access']);
        Project::factory()->withBoards()->create(['status' => ProjectStatus::OnGoing->value]);

        $response = dfengineService()->listProjectDFEngine();

        $row = collect($response['data']['paginated'])->first();
        expect($row->action['can_open'])->toBeTrue()
            ->and($row->action['can_edit_setting'])->toBeFalse();
    });
});

// ---- listTaskDFEngine (service) -------------------------------------------

describe('listTaskDFEngine (service)', function () {
    it('lists all tasks of the given project for a non-production caller', function () {
        dfengineActingAs(BaseRole::Root->value);

        $project = Project::factory()->withBoards()->create();
        $boardId = $project->boards->first()->id;
        ProjectTask::factory()->create([
            'project_id' => $project->id, 'project_board_id' => $boardId,
            'name' => 'Task Alpha', 'status' => TaskStatus::OnProgress->value,
        ]);
        ProjectTask::factory()->create([
            'project_id' => $project->id, 'project_board_id' => $boardId,
            'name' => 'Task Beta', 'status' => TaskStatus::OnProgress->value,
        ]);
        // A task on a different project must never leak in.
        $other = Project::factory()->withBoards()->create();
        ProjectTask::factory()->create([
            'project_id' => $other->id, 'project_board_id' => $other->boards->first()->id,
            'name' => 'Foreign Task', 'status' => TaskStatus::OnProgress->value,
        ]);

        $response = dfengineService()->listTaskDFEngine($project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['totalData'])->toBe(2);

        $rows = collect($response['data']['paginated']);
        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('name')->all())->toContain('Task Alpha', 'Task Beta')
            ->and($rows->pluck('name')->all())->not->toContain('Foreign Task')
            ->and($rows->first()->project_uid)->toBe($project->uid)
            ->and($rows->first()->status)->not->toBeEmpty();
    });

    it('scopes tasks to the caller own assignments for a production caller', function () {
        $user = dfengineActingAs(BaseRole::Production->value, withEmployee: true);
        $employeeId = $user->employee->id;

        $project = Project::factory()->withBoards()->create();
        $boardId = $project->boards->first()->id;

        $mine = ProjectTask::factory()->create([
            'project_id' => $project->id, 'project_board_id' => $boardId,
            'name' => 'Mine', 'status' => TaskStatus::OnProgress->value,
        ]);
        ProjectTaskPic::create([
            'project_task_id' => $mine->id,
            'employee_id' => $employeeId,
            'status' => TaskPicStatus::Approved->value,
        ]);

        $notMine = ProjectTask::factory()->create([
            'project_id' => $project->id, 'project_board_id' => $boardId,
            'name' => 'Not Mine', 'status' => TaskStatus::OnProgress->value,
        ]);
        ProjectTaskPic::create([
            'project_task_id' => $notMine->id,
            'employee_id' => Employee::factory()->create()->id,
            'status' => TaskPicStatus::Approved->value,
        ]);

        $response = dfengineService()->listTaskDFEngine($project->uid);

        $rows = collect($response['data']['paginated']);
        expect($response['data']['totalData'])->toBe(1)
            ->and($rows)->toHaveCount(1)
            ->and($rows->first()->name)->toBe('Mine');
    });
});

// ---- E2E over HTTP (auth.session guard + permission-driven flags) ----------

describe('DFEngine list endpoints (e2e)', function () {
    it('returns the dfengine project list for a permitted user', function () {
        dfengineActingAs(BaseRole::Root->value, ['dfengine_access', 'dfengine_edit_setting']);
        Project::factory()->withBoards()->create([
            'name' => 'Live Project',
            'status' => ProjectStatus::OnGoing->value,
        ]);

        getJson('/api/production/dfengine/projects')
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1)
            ->assertJsonPath('data.paginated.0.name', 'Live Project')
            ->assertJsonPath('data.paginated.0.action.can_open', true)
            ->assertJsonPath('data.paginated.0.action.can_edit_setting', true);
    });

    it('returns the dfengine task list for a project', function () {
        dfengineActingAs(BaseRole::Root->value);
        $project = Project::factory()->withBoards()->create();
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'project_board_id' => $project->boards->first()->id,
            'name' => 'HTTP Task',
            'status' => TaskStatus::OnProgress->value,
        ]);

        getJson('/api/production/dfengine/projects/'.$project->uid)
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1)
            ->assertJsonPath('data.paginated.0.name', 'HTTP Task');
    });

    it('exposes can_open=false when the caller lacks dfengine_access', function () {
        // The permission exists but is NOT granted to this caller.
        dfengineActingAs(BaseRole::Root->value);
        Project::factory()->withBoards()->create(['status' => ProjectStatus::OnGoing->value]);

        getJson('/api/production/dfengine/projects')
            ->assertStatus(201)
            ->assertJsonPath('data.paginated.0.action.can_open', false)
            ->assertJsonPath('data.paginated.0.action.can_edit_setting', false);
    });

    it('rejects an unauthenticated caller', function () {
        getJson('/api/production/dfengine/projects')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    });
});
