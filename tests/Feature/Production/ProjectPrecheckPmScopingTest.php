<?php

use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * precheck() gates a PM from completing a project while they still have unfinished tasks. A project
 * can have more than one PM and each completes it separately (see completeProject), so the gate must
 * only count the acting PM's OWN unfinished tasks (project_tasks.created_by = their user id) and
 * never another PM's. Before this fix, every PM saw the whole project's unfinished tasks, so PM B's
 * ongoing work blocked PM A even when PM A's tasks were all done.
 */
function precheckService(): ProjectService
{
    return app(ProjectService::class);
}

function precheckPm(): User
{
    $employee = Employee::factory()->withUser()->create();

    return User::where('employee_id', $employee->id)->firstOrFail();
}

function precheckUserWithRole(string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);
    $user = precheckPm();
    $user->assignRole($role);
    // Spatie caches role lookups within the request; clear so hasRole() sees the fresh role.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * project_tasks.created_by is stamped from auth()->id() on create (HasTaskIdentifier trait), so each
 * PM's tasks must be created while acting as that PM — exactly how it happens in production.
 */
function precheckTask(Project $project, User $pm, int $status): ProjectTask
{
    actingAs($pm);

    return ProjectTask::factory()->create([
        'project_id' => $project->id,
        'status' => $status,
    ]);
}

it('counts only the acting PM own unfinished tasks', function () {
    $project = Project::factory()->create();
    $pmA = precheckPm();
    $pmB = precheckPm();

    $taskA = precheckTask($project, $pmA, TaskStatus::CheckByPm->value);
    // Another PM's unfinished task on the same project.
    precheckTask($project, $pmB, TaskStatus::CheckByPm->value);

    actingAs($pmA);
    $response = precheckService()->precheck($project->uid);

    expect($response['data']['needToCompleteTask'])->toBeTrue()
        ->and($response['data']['scope'])->toBe('own')
        ->and($response['data']['tasks']->pluck('id')->all())->toBe([$taskA->id]);
});

it('lets a director see every PM unfinished task (override)', function () {
    $project = Project::factory()->create();
    $pmA = precheckPm();
    $pmB = precheckPm();

    precheckTask($project, $pmA, TaskStatus::CheckByPm->value);
    precheckTask($project, $pmB, TaskStatus::CheckByPm->value);

    // A director created none of the tasks but overrides every team.
    $director = precheckUserWithRole(BaseRole::Director->value);
    actingAs($director);
    $response = precheckService()->precheck($project->uid);

    expect($response['data']['needToCompleteTask'])->toBeTrue()
        ->and($response['data']['scope'])->toBe('all')
        ->and($response['data']['tasks'])->toHaveCount(2);
});

it('is not blocked by another PM unfinished tasks once its own are done', function () {
    $project = Project::factory()->create();
    $pmA = precheckPm();
    $pmB = precheckPm();

    // PM A finished everything; PM B still has an ongoing task.
    precheckTask($project, $pmA, TaskStatus::Completed->value);
    precheckTask($project, $pmB, TaskStatus::CheckByPm->value);

    actingAs($pmA);
    $response = precheckService()->precheck($project->uid);

    expect($response['data']['needToCompleteTask'])->toBeFalse()
        ->and($response['data']['tasks'])->toHaveCount(0);
});
