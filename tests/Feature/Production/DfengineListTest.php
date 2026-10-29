<?php

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskSongStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectSongList;
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
 * caller's role: a production member sees their boss's projects, a project manager sees the
 * projects they are a PIC of (plus approved transfers), and root/director see every active project.
 * Each row's action flags come from the caller's dfengine_access / dfengine_edit_setting permissions.
 *
 * listTaskDFEngine returns a TaskListData => { tasks, songs }: a non-entertainment caller gets
 * production `tasks` (root/director only see tasks that HAVE a pic; a production member only their
 * own assignments), while an entertainment caller gets the project `songs` (an Entertainment user is
 * further scoped to their own songs). Only tasks in the allowed statuses (OnProgress / CheckByPm)
 * are listed. getUser() reads the acting user's first role, so every actor needs a role; the two
 * dfengine permissions must exist (hasPermissionTo throws otherwise), so the helper always seeds them.
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

/** Create a project task and attach one PIC (needed for the root/director "has pics" scope). */
function dfTaskWithPic(Project $project, string $name, int $status, ?int $employeeId = null): ProjectTask
{
    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'name' => $name,
        'status' => $status,
    ]);

    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $employeeId ?? Employee::factory()->create()->id,
        'status' => TaskPicStatus::Approved->value,
    ]);

    return $task;
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

    it('scopes the list to the boss projects for a production caller', function () {
        $user = dfengineActingAs(BaseRole::Production->value, withEmployee: true);
        $boss = Employee::factory()->create();
        Employee::where('id', $user->employee->id)->update(['boss_id' => $boss->id]);

        $bossProject = Project::factory()->withBoards()->create([
            'name' => 'Boss Project',
            'status' => ProjectStatus::OnGoing->value,
        ]);
        ProjectPersonInCharge::create(['project_id' => $bossProject->id, 'pic_id' => $boss->id]);

        // Active, but the boss is not a PIC -> must not appear.
        Project::factory()->withBoards()->create([
            'name' => 'Unrelated Project',
            'status' => ProjectStatus::OnGoing->value,
        ]);

        $response = dfengineService()->listProjectDFEngine();

        $names = collect($response['data']['paginated'])->pluck('name');
        expect($names)->toContain('Boss Project')
            ->and($names)->not->toContain('Unrelated Project');
    });

    it('scopes the list to the projects a project manager is PIC of', function () {
        $user = dfengineActingAs(BaseRole::ProjectManager->value, withEmployee: true);

        $mine = Project::factory()->withBoards()->create([
            'name' => 'My PM Project',
            'status' => ProjectStatus::OnGoing->value,
        ]);
        ProjectPersonInCharge::create(['project_id' => $mine->id, 'pic_id' => $user->employee->id]);

        Project::factory()->withBoards()->create([
            'name' => 'Someone Else Project',
            'status' => ProjectStatus::OnGoing->value,
        ]);

        $response = dfengineService()->listProjectDFEngine();

        $names = collect($response['data']['paginated'])->pluck('name');
        expect($names)->toContain('My PM Project')
            ->and($names)->not->toContain('Someone Else Project');
    });
});

// ---- listTaskDFEngine (service) -------------------------------------------

describe('listTaskDFEngine (service)', function () {
    it('lists a project tasks (with pics, allowed statuses) for a root caller', function () {
        dfengineActingAs(BaseRole::Root->value);

        $project = Project::factory()->withBoards()->create();
        dfTaskWithPic($project, 'Task Alpha', TaskStatus::OnProgress->value);
        dfTaskWithPic($project, 'Task Beta', TaskStatus::CheckByPm->value);
        // Completed is not an allowed status -> excluded even with a pic.
        dfTaskWithPic($project, 'Task Done', TaskStatus::Completed->value);
        // No pic -> excluded for a root/director caller (has pics).
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'project_board_id' => $project->boards->first()->id,
            'name' => 'No Pic Task',
            'status' => TaskStatus::OnProgress->value,
        ]);
        // A task on a different project must never leak in.
        $other = Project::factory()->withBoards()->create();
        dfTaskWithPic($other, 'Foreign Task', TaskStatus::OnProgress->value);

        $response = dfengineService()->listTaskDFEngine($project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['songs'])->toBe([]);

        $names = collect($response['data']['tasks'])->pluck('name')->all();
        expect($names)->toContain('Task Alpha', 'Task Beta')
            ->and($names)->not->toContain('Task Done')
            ->and($names)->not->toContain('No Pic Task')
            ->and($names)->not->toContain('Foreign Task')
            ->and(collect($response['data']['tasks'])->first()['project_uid'])->toBe($project->uid);
    });

    it('scopes tasks to the caller own assignments for a production caller', function () {
        $user = dfengineActingAs(BaseRole::Production->value, withEmployee: true);
        $employeeId = $user->employee->id;

        $project = Project::factory()->withBoards()->create();
        dfTaskWithPic($project, 'Mine', TaskStatus::OnProgress->value, $employeeId);
        dfTaskWithPic($project, 'Not Mine', TaskStatus::OnProgress->value, Employee::factory()->create()->id);

        $response = dfengineService()->listTaskDFEngine($project->uid);

        $tasks = collect($response['data']['tasks']);
        expect($tasks)->toHaveCount(1)
            ->and($tasks->pluck('name')->all())->toBe(['Mine'])
            ->and($response['data']['songs'])->toBe([]);
    });

    it('lists the project songs (scoped to own) for an entertainment caller', function () {
        $user = dfengineActingAs(BaseRole::Entertainment->value, withEmployee: true);

        $project = Project::factory()->withBoards()->create();
        $song = ProjectSongList::factory()->create([
            'project_id' => $project->id,
            'name' => 'Opening Song',
            'created_by' => 1,
        ]);
        EntertainmentTaskSong::factory()->create([
            'project_id' => $project->id,
            'project_song_list_id' => $song->id,
            'employee_id' => $user->employee_id,
            'status' => TaskSongStatus::OnProgress->value,
        ]);

        // A production task must NOT appear for an entertainment caller.
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'project_board_id' => $project->boards->first()->id,
            'name' => 'Prod Task',
            'status' => TaskStatus::OnProgress->value,
        ]);

        $response = dfengineService()->listTaskDFEngine($project->uid);

        expect($response['error'])->toBeFalse()
            ->and($response['data']['tasks'])->toBe([]);

        $songNames = collect($response['data']['songs'])->pluck('name')->all();
        expect($songNames)->toContain('Opening Song');
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
        dfTaskWithPic($project, 'HTTP Task', TaskStatus::OnProgress->value);

        getJson('/api/production/dfengine/projects/'.$project->uid)
            ->assertStatus(201)
            ->assertJsonPath('data.tasks.0.name', 'HTTP Task');
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
