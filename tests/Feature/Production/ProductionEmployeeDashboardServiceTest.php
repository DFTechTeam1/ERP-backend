<?php

use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Models\User;
use Carbon\Carbon;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskDeadline;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Models\ProjectTaskReviseHistory;
use Modules\Production\Services\ProductionEmployeeDashboardService;

use function Pest\Laravel\actingAs;

/**
 * Coverage for the personal Production Employee dashboard.
 *
 * Rules under test:
 *   - Unauthenticated or user without employee_id gets 403.
 *   - KPI counts scope to the acting user's employee_id.
 *   - Overdue = deadline < today AND actual_finish_time is null AND task
 *     status is not Completed/OnHold.
 *   - My-tasks lists only OnProgress + Revise tasks I'm assigned to,
 *     nearest deadline first, with revise_count + is_heavy_revise (>= 3).
 *   - Pool-tasks lists is_pool_task=true rows that don't yet have any pic.
 */

function mkDashProject(): Project
{
    // The Project factory has withBoards() etc; a bare project + one deal
    // is enough for the dashboard to render project_name/identifier fields.
    return Project::factory()->withBoards()->create();
}

function mkDashTaskFor(Employee $employee, int $status, ?string $deadline = null): ProjectTask
{
    $project = mkDashProject();
    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'status' => $status,
    ]);
    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
        'approved_at' => now(),
    ]);
    if ($deadline) {
        // updated_by is NOT NULL and FK-constrained to users. Ensure the
        // employee has a linked user before stamping the deadline.
        $employee->refresh();
        $updatedBy = $employee->user_id;
        if (! $updatedBy) {
            $u = User::factory()->create(['employee_id' => $employee->id]);
            $employee->update(['user_id' => $u->id]);
            $updatedBy = $u->id;
        }
        ProjectTaskDeadline::create([
            'project_task_id' => $task->id,
            'employee_id' => $employee->id,
            'deadline' => $deadline,
            'actual_finish_time' => null,
            'is_first_deadline' => true,
            'updated_by' => $updatedBy,
        ]);
    }

    return $task;
}

beforeEach(function () {
    $this->service = app(ProductionEmployeeDashboardService::class);
    cache()->flush();
});

// ---- Auth --------------------------------------------------------------

it('rejects an unauthenticated caller with 403', function () {
    $result = $this->service->getKpi();

    expect($result['code'])->toBe(403);
});

it('rejects an authenticated user without an employee link with 403', function () {
    $user = User::factory()->create(['employee_id' => null]);
    actingAs($user);

    $result = $this->service->getKpi();

    expect($result['code'])->toBe(403);
});

// ---- KPI ---------------------------------------------------------------

it('counts my open tasks (excluding completed and on-hold)', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    mkDashTaskFor($employee, TaskStatus::OnProgress->value);
    mkDashTaskFor($employee, TaskStatus::Revise->value);
    mkDashTaskFor($employee, TaskStatus::CheckByPm->value);
    // Excluded - completed / on hold.
    mkDashTaskFor($employee, TaskStatus::Completed->value);
    mkDashTaskFor($employee, TaskStatus::OnHold->value);
    // Someone else's task - must NOT count for me.
    $stranger = Employee::factory()->create();
    mkDashTaskFor($stranger, TaskStatus::OnProgress->value);

    actingAs($user);

    $result = $this->service->getKpi();

    expect($result['code'])->toBe(201)
        ->and($result['data']['my_open_tasks'])->toBe(3);
});

it('counts overdue tasks with an unfinished past-due deadline for me', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    mkDashTaskFor(
        $employee,
        TaskStatus::OnProgress->value,
        deadline: Carbon::today()->subDay()->toDateString(),
    );
    mkDashTaskFor(
        $employee,
        TaskStatus::OnProgress->value,
        deadline: Carbon::today()->subDays(10)->toDateString(),
    );
    // Future deadline - not overdue.
    mkDashTaskFor(
        $employee,
        TaskStatus::OnProgress->value,
        deadline: Carbon::today()->addDays(5)->toDateString(),
    );

    actingAs($user);

    $result = $this->service->getKpi();

    expect($result['data']['my_overdue_tasks'])->toBe(2);
});

// ---- My-tasks ----------------------------------------------------------

it('returns my active tasks sorted by nearest deadline first', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    $tomorrow = Carbon::today()->addDay()->toDateString();
    $inWeek = Carbon::today()->addDays(7)->toDateString();
    $yesterday = Carbon::today()->subDay()->toDateString();

    mkDashTaskFor($employee, TaskStatus::OnProgress->value, deadline: $inWeek);
    mkDashTaskFor($employee, TaskStatus::OnProgress->value, deadline: $tomorrow);
    mkDashTaskFor($employee, TaskStatus::Revise->value, deadline: $yesterday);
    // Excluded - completed and someone else's.
    mkDashTaskFor($employee, TaskStatus::Completed->value);
    mkDashTaskFor(Employee::factory()->create(), TaskStatus::OnProgress->value);

    actingAs($user);

    $result = $this->service->getMyTasks();

    $deadlines = collect($result['data']['items'])->pluck('deadline')->all();

    expect($result['data']['total'])->toBe(3)
        // yesterday's overdue → tomorrow → next week.
        ->and($deadlines)->toBe([$yesterday, $tomorrow, $inWeek]);
});

it('flags a task as heavy-revise once revise_count >= 3', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    $task = mkDashTaskFor($employee, TaskStatus::Revise->value);
    // Create 3 revise history rows.
    for ($i = 0; $i < 3; $i++) {
        ProjectTaskReviseHistory::create([
            'project_task_id' => $task->id,
            'revise_by' => $user->id,
            'reason' => "revise {$i}",
        ]);
    }

    actingAs($user);

    $result = $this->service->getMyTasks();
    $row = $result['data']['items'][0];

    expect($row['revise_count'])->toBe(3)
        ->and($row['is_heavy_revise'])->toBeTrue();
});

// ---- Pool tasks --------------------------------------------------------

it('sums points from EmployeePointProject filtered by the linked project event date', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    // Single EmployeePoint row per employee (matches the Node contract).
    $ep = EmployeePoint::create([
        'employee_id' => $employee->id,
        'total_point' => 0,
    ]);

    $inMonth = Project::factory()->withBoards()->create([
        'project_date' => '2026-04-10',
    ]);
    $alsoIn = Project::factory()->withBoards()->create([
        'project_date' => '2026-04-28',
    ]);
    $outsideMonth = Project::factory()->withBoards()->create([
        'project_date' => '2026-05-01',
    ]);

    // additional_point / original_point are NOT NULL on the schema.
    $eppDefaults = ['additional_point' => 0, 'original_point' => 0];

    // Column is stored as integer, so use whole numbers.
    EmployeePointProject::create([
        'employee_point_id' => $ep->id,
        'project_id' => $inMonth->id,
        'total_point' => 12,
    ] + $eppDefaults);
    EmployeePointProject::create([
        'employee_point_id' => $ep->id,
        'project_id' => $alsoIn->id,
        'total_point' => 8,
    ] + $eppDefaults);
    // Outside the requested month - MUST be excluded even if the point row
    // itself was created inside the month.
    EmployeePointProject::create([
        'employee_point_id' => $ep->id,
        'project_id' => $outsideMonth->id,
        'total_point' => 999,
    ] + $eppDefaults);

    // Another employee's point on an in-month project - MUST be ignored.
    $stranger = Employee::factory()->create();
    $strangerEp = EmployeePoint::create([
        'employee_id' => $stranger->id,
        'total_point' => 0,
    ]);
    EmployeePointProject::create([
        'employee_point_id' => $strangerEp->id,
        'project_id' => $inMonth->id,
        'total_point' => 500,
    ] + $eppDefaults);

    actingAs($user);

    $result = $this->service->getKpi(month: 4, year: 2026);

    expect($result['data']['points_this_month'])->toBe(20.0);
});

it('treats a task with no personal deadline as overdue when the event date has passed', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    // Task with NO deadline row → fallback to project.project_date.
    $pastProject = Project::factory()->withBoards()->create([
        'project_date' => Carbon::today()->subDays(5)->toDateString(),
    ]);
    $pastTask = ProjectTask::factory()->create([
        'project_id' => $pastProject->id,
        'project_board_id' => $pastProject->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);
    ProjectTaskPic::create([
        'project_task_id' => $pastTask->id,
        'employee_id' => $employee->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
        'approved_at' => now(),
    ]);

    // Control: future event date → not overdue.
    $futureProject = Project::factory()->withBoards()->create([
        'project_date' => Carbon::today()->addDays(5)->toDateString(),
    ]);
    $futureTask = ProjectTask::factory()->create([
        'project_id' => $futureProject->id,
        'project_board_id' => $futureProject->boards->first()->id,
        'status' => TaskStatus::OnProgress->value,
    ]);
    ProjectTaskPic::create([
        'project_task_id' => $futureTask->id,
        'employee_id' => $employee->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
        'approved_at' => now(),
    ]);

    actingAs($user);

    $kpi = $this->service->getKpi();
    expect($kpi['data']['my_overdue_tasks'])->toBe(1);

    $tasks = $this->service->getMyTasks();
    $rowById = collect($tasks['data']['items'])->keyBy('id');
    expect($rowById[$pastTask->id]['is_overdue'])->toBeTrue()
        ->and($rowById[$pastTask->id]['deadline_source'])->toBe('event')
        ->and($rowById[$futureTask->id]['is_overdue'])->toBeFalse()
        ->and($rowById[$futureTask->id]['deadline_source'])->toBe('event');
});

it('does not count a task with actual_finish_time set as overdue even if the deadline passed', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    $task = mkDashTaskFor(
        $employee,
        TaskStatus::OnProgress->value,
        deadline: Carbon::today()->subDays(2)->toDateString(),
    );
    // Mark the deadline as finished.
    ProjectTaskDeadline::query()
        ->where('project_task_id', $task->id)
        ->update(['actual_finish_time' => now()]);

    actingAs($user);

    $kpi = $this->service->getKpi();
    expect($kpi['data']['my_overdue_tasks'])->toBe(0);
});

it('exposes days_to_event on pool tasks and does not emit deadline fields', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    $project = Project::factory()->withBoards()->create([
        'project_date' => Carbon::today()->addDays(3)->toDateString(),
    ]);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'is_pool_task' => true,
        'status' => TaskStatus::WaitingDistribute->value,
    ]);

    actingAs($user);

    $result = $this->service->getPoolTasks();
    $row = $result['data']['items'][0];

    expect($row)->toHaveKey('event_date')
        ->and($row)->toHaveKey('days_to_event')
        ->and($row['days_to_event'])->toBe(3)
        ->and($row)->not->toHaveKey('deadline')
        ->and($row)->not->toHaveKey('days_to_deadline');
});

it('lists pool tasks that have no picker yet', function () {
    $employee = Employee::factory()->withUser()->create();
    $user = User::where('employee_id', $employee->id)->first();

    $project = mkDashProject();

    // Available in the pool.
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'is_pool_task' => true,
        'status' => TaskStatus::WaitingDistribute->value,
    ]);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'is_pool_task' => true,
        'status' => TaskStatus::WaitingDistribute->value,
    ]);
    // Already picked - should NOT appear even if is_pool_task still true.
    $picked = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'is_pool_task' => true,
        'status' => TaskStatus::OnProgress->value,
    ]);
    ProjectTaskPic::create([
        'project_task_id' => $picked->id,
        'employee_id' => $employee->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
    ]);
    // Not in pool at all.
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $project->boards->first()->id,
        'is_pool_task' => false,
    ]);

    actingAs($user);

    $result = $this->service->getPoolTasks();

    expect($result['data']['total'])->toBe(2)
        ->and(count($result['data']['items']))->toBe(2);
});
