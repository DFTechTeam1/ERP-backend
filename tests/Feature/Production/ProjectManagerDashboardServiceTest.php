<?php

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Carbon\Carbon;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskDeadline;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Services\ProjectManagerDashboardService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Coverage for the Project Manager dashboard scoping + aggregates.
 *
 * Rules under test:
 *   - PM sees only projects where they are on ProjectPersonInCharge.
 *   - PM Admin / Director / Root see every project (unscoped).
 *   - KPI counts (my_projects, team_open, team_overdue, projects_at_risk).
 *   - Team workload row includes an expandable `tasks` array.
 *   - At-risk projects = past event OR within the 7-day window.
 */

function ensurePmRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
}

function actAsPm(string $roleName, ?Employee $employee = null): User
{
    ensurePmRole($roleName);
    $employee ??= Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $user->assignRole($roleName);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    actingAs($user);

    return $user;
}

/** Build a project owned by the given PM (attaches ProjectPersonInCharge). */
function pmProject(Employee $pm, array $attrs = []): Project
{
    $project = Project::factory()->withBoards()->create(array_merge([
        'status' => ProjectStatus::OnGoing->value,
    ], $attrs));
    ProjectPersonInCharge::create([
        'project_id' => $project->id,
        'pic_id' => $pm->id,
    ]);

    return $project;
}

/** Build a task on a project assigned to a given worker. */
function pmTaskFor(Project $project, Employee $worker, int $status): ProjectTask
{
    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'status' => $status,
    ]);
    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
        'approved_at' => now(),
    ]);

    return $task;
}

beforeEach(function () {
    $this->service = app(ProjectManagerDashboardService::class);
    cache()->flush();
});

// ---- Auth --------------------------------------------------------------

it('rejects a non-PM role with 403', function () {
    actAsPm(BaseRole::Sales->value);

    $result = $this->service->getKpi();

    expect($result['code'])->toBe(403);
});

// ---- KPI ---------------------------------------------------------------

it('counts only projects the PM is assigned to', function () {
    $pm = Employee::factory()->create();
    // Two projects for me, one for a stranger.
    pmProject($pm);
    pmProject($pm);
    $stranger = Employee::factory()->create();
    pmProject($stranger);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getKpi();

    expect($result['data']['my_projects'])->toBe(2);
});

it('gives PM Admin an unscoped view of all projects', function () {
    Project::factory()->withBoards()->count(3)->create([
        'status' => ProjectStatus::OnGoing->value,
    ]);

    actAsPm(BaseRole::ProjectManagerAdmin->value);

    $result = $this->service->getKpi();

    expect($result['data']['my_projects'])->toBeGreaterThanOrEqual(3);
});

it('counts team_open_tasks as unassigned non-pool non-finalize tasks in my projects', function () {
    $pm = Employee::factory()->create();
    $mine = pmProject($pm);
    $stranger = Employee::factory()->create();
    $other = pmProject($stranger);

    // Ensure the auto-created boards from withBoards() don't accidentally
    // match "finalize" (factory uses fake()->sentence(1)). Force distinct
    // non-finalize board names.
    $mine->boards()->update(['name' => 'Compositing']);
    $other->boards()->update(['name' => 'Compositing']);

    // Unassigned on my project - COUNTS.
    ProjectTask::factory()->create([
        'project_id' => $mine->id,
        'project_board_id' => $mine->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);
    ProjectTask::factory()->create([
        'project_id' => $mine->id,
        'project_board_id' => $mine->boards->first()->id,
        'status' => TaskStatus::WaitingDistribute->value,
    ]);

    // Has a PIC - EXCLUDED (already dispatched).
    $assigned = ProjectTask::factory()->create([
        'project_id' => $mine->id,
        'project_board_id' => $mine->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);
    ProjectTaskPic::create([
        'project_task_id' => $assigned->id,
        'employee_id' => Employee::factory()->create()->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
        'approved_at' => now(),
    ]);

    // Pool task - EXCLUDED.
    ProjectTask::factory()->create([
        'project_id' => $mine->id,
        'project_board_id' => $mine->boards->first()->id,
        'is_pool_task' => true,
        'status' => TaskStatus::WaitingDistribute->value,
    ]);

    // Finalize board task - EXCLUDED.
    $finalizeBoard = $mine->boards()->create([
        'name' => 'Finalize',
        'sort' => 99,
    ]);
    ProjectTask::factory()->create([
        'project_id' => $mine->id,
        'project_board_id' => $finalizeBoard->id,
        'status' => TaskStatus::OnProgress->value,
    ]);

    // Unassigned on stranger's project - EXCLUDED (wrong scope).
    ProjectTask::factory()->create([
        'project_id' => $other->id,
        'project_board_id' => $other->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getKpi();

    expect($result['data']['team_open_tasks'])->toBe(2);
});

it('counts team_overdue_tasks as unassigned in-scope tasks whose project event has passed', function () {
    $pm = Employee::factory()->create();
    $past = pmProject($pm, ['project_date' => Carbon::today()->subDays(3)->toDateString()]);
    $future = pmProject($pm, ['project_date' => Carbon::today()->addDays(3)->toDateString()]);
    $past->boards()->update(['name' => 'Compositing']);
    $future->boards()->update(['name' => 'Compositing']);

    // Two unassigned tasks on past-event project (overdue).
    ProjectTask::factory()->create([
        'project_id' => $past->id,
        'project_board_id' => $past->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);
    ProjectTask::factory()->create([
        'project_id' => $past->id,
        'project_board_id' => $past->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);
    // One unassigned on future-event project (not overdue).
    ProjectTask::factory()->create([
        'project_id' => $future->id,
        'project_board_id' => $future->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getKpi();

    expect($result['data']['team_overdue_tasks'])->toBe(2);
});

it('counts projects at risk (event within 7 days OR past)', function () {
    $pm = Employee::factory()->create();
    // At risk: 3 days away.
    pmProject($pm, [
        'project_date' => Carbon::today()->addDays(3)->toDateString(),
    ]);
    // At risk: past event.
    pmProject($pm, [
        'project_date' => Carbon::today()->subDays(2)->toDateString(),
    ]);
    // Not at risk: 30 days away.
    pmProject($pm, [
        'project_date' => Carbon::today()->addDays(30)->toDateString(),
    ]);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getKpi();

    expect($result['data']['projects_at_risk'])->toBe(2);
});

// ---- Team workload -----------------------------------------------------

it('returns each direct report (boss_id = PM) with their task list expanded', function () {
    $pm = Employee::factory()->create();
    $project = pmProject($pm);

    // Alice + Bob are direct reports; Cara is not (different boss_id).
    $alice = Employee::factory()->create([
        'name' => 'Alice', 'nickname' => null, 'boss_id' => $pm->id,
    ]);
    $bob = Employee::factory()->create([
        'name' => 'Bob', 'nickname' => null, 'boss_id' => $pm->id,
    ]);
    $cara = Employee::factory()->create([
        'name' => 'Cara', 'nickname' => null, 'boss_id' => null,
    ]);

    pmTaskFor($project, $alice, TaskStatus::OnProgress->value);
    pmTaskFor($project, $alice, TaskStatus::Revise->value);
    pmTaskFor($project, $bob, TaskStatus::OnProgress->value);
    pmTaskFor($project, $cara, TaskStatus::OnProgress->value);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getTeamWorkload();
    $byName = collect($result['data']['items'])->keyBy('name');
    $names = $byName->keys()->all();

    expect($names)
        ->toContain('Alice', 'Bob')
        ->and($names)->not->toContain('Cara')
        ->and($byName['Alice']['open_tasks'])->toBe(2)
        ->and(count($byName['Alice']['tasks']))->toBe(2)
        ->and($byName['Bob']['open_tasks'])->toBe(1)
        ->and($byName['Alice']['tasks'][0])->toHaveKey('is_overdue')
        ->and($byName['Alice']['tasks'][0])->toHaveKey('project_name');
});

// ---- My projects -------------------------------------------------------

it('computes progress percent from completed vs total tasks', function () {
    $pm = Employee::factory()->create();
    $project = pmProject($pm);
    $worker = Employee::factory()->create();

    // 1 completed / 3 total -> 33%
    pmTaskFor($project, $worker, TaskStatus::Completed->value);
    pmTaskFor($project, $worker, TaskStatus::OnProgress->value);
    pmTaskFor($project, $worker, TaskStatus::Revise->value);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getMyProjects();
    $row = $result['data']['items'][0];

    expect($row['total_tasks'])->toBe(3)
        ->and($row['completed_tasks'])->toBe(1)
        ->and($row['progress_percent'])->toBe(33);
});

// ---- At-risk projects --------------------------------------------------

it('lists projects with past event or within the risk window, nearest first', function () {
    $pm = Employee::factory()->create();
    $past = pmProject($pm, [
        'project_date' => Carbon::today()->subDays(3)->toDateString(),
    ]);
    $soon = pmProject($pm, [
        'project_date' => Carbon::today()->addDays(2)->toDateString(),
    ]);
    // Not at risk.
    pmProject($pm, [
        'project_date' => Carbon::today()->addDays(20)->toDateString(),
    ]);

    actAsPm(BaseRole::ProjectManager->value, $pm);

    $result = $this->service->getAtRiskProjects();
    $ids = collect($result['data']['items'])->pluck('id')->all();

    expect($result['data']['total'])->toBe(2)
        // Past dates come first (ordered ascending on project_date).
        ->and($ids[0])->toBe($past->id)
        ->and($ids[1])->toBe($soon->id);
});
