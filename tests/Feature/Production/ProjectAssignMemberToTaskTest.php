<?php

use App\Actions\PartialTaskPermissionCheck;
use App\Actions\Project\DetailCache;
use App\Actions\Project\FormatBoards;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * assignMemberToTask() decides the resulting project_tasks.status from a mix of flags
 * (isForProjectManager, isRevise, needChangeTaskStatus), whether the assignee is the
 * configured 3D lead modeller, the task's current status, and the pic count afterwards.
 *
 * Task-status resolution (only when !isRevise && needChangeTaskStatus), later rules win:
 *   - lead modeller                                   -> WaitingDistribute
 *   - no pics left                                    -> null
 *   - (was null + now has pics) OR was Completed       -> WaitingApproval
 *   - lead modeller (re-forced, the recent change)     -> WaitingDistribute   <-- overrides WaitingApproval
 *   - project manager                                  -> CheckByPm
 * plus is_pool_task is always set false.
 *
 * The per-pic status is set independently: PM -> Approved, revise -> Revise,
 * lead modeller -> WaitingToDistribute, otherwise left at the column default.
 *
 * The detail pipeline (formattedDetailTask/FormatBoards/DetailCache) is stubbed; the
 * status/pic writes happen before it against the real DB. loggingTask is a no-op under
 * APP_ENV=testing. getSettingByKey reads the 'setting' cache, seeded directly.
 */
function amtService(): ProjectService
{
    return app(ProjectService::class);
}

function amtEmployee(): Employee
{
    return Employee::factory()->withUser()->create();
}

function amtTask(?int $status, ?string $endDate = '2026-10-01 10:00:00'): ProjectTask
{
    return ProjectTask::factory()->create([
        'status' => $status,
        'end_date' => $endDate,
    ]);
}

beforeEach(function () {
    Queue::fake();

    $this->mock(PartialTaskPermissionCheck::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturnUsing(fn ($taskDetail, $telegramEmployee = null) => $taskDetail);
    });
    $this->mock(FormatBoards::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn([]);
    });
    $this->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn([]);
    });

    // Non-PM assignees hit userData->hasPermissionTo('assign_modeller'); the permission must exist.
    Permission::firstOrCreate(['name' => 'assign_modeller', 'guard_name' => 'sanctum']);

    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail());

    // The configured lead modeller is matched by uid inside the service.
    $this->leadModeller = Employee::factory()->withUser()->create();
    Cache::forever('setting', [
        ['key' => 'lead_3d_modeller', 'value' => $this->leadModeller->uid],
    ]);
});

describe('task status resolution', function () {
    it('moves a status-less task to WaitingApproval once it has a pic', function () {
        $task = amtTask(status: null);
        $emp = amtEmployee();

        $response = amtService()->assignMemberToTask(['users' => [$emp->uid], 'removed' => []], $task->uid);

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
            'is_pool_task' => false,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $emp->id,
        ]);
    });

    it('moves a Completed task back to WaitingApproval on reassignment', function () {
        $task = amtTask(status: TaskStatus::Completed->value);
        $emp = amtEmployee();

        amtService()->assignMemberToTask(['users' => [$emp->uid], 'removed' => []], $task->uid);

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
        ]);
    });

    it('leaves an in-progress task status untouched when no rule applies', function () {
        $task = amtTask(status: TaskStatus::OnProgress->value);
        $emp = amtEmployee();

        amtService()->assignMemberToTask(['users' => [$emp->uid], 'removed' => []], $task->uid);

        // Not null, not Completed, not PM/lead/revise -> only is_pool_task changes.
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::OnProgress->value,
            'is_pool_task' => false,
        ]);
    });

    it('assigns to a project manager as CheckByPm with an Approved pic', function () {
        $task = amtTask(status: null);
        $emp = amtEmployee();

        amtService()->assignMemberToTask(
            ['users' => [$emp->uid], 'removed' => []],
            $task->uid,
            isForProjectManager: true
        );

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::CheckByPm->value,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $emp->id,
            'status' => TaskPicStatus::Approved->value,
        ]);
    });

    it('assigns the lead modeller as WaitingDistribute with a WaitingToDistribute pic', function () {
        $task = amtTask(status: null);

        amtService()->assignMemberToTask(
            ['users' => [$this->leadModeller->uid], 'removed' => []],
            $task->uid
        );

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $this->leadModeller->id,
            'status' => TaskPicStatus::WaitingToDistribute->value,
        ]);
    });

    it('keeps the lead modeller as WaitingDistribute even on a Completed task', function () {
        // Regression guard for the recent change: the lead-modeller rule must win over the
        // "was Completed -> WaitingApproval" rule.
        $task = amtTask(status: TaskStatus::Completed->value);

        amtService()->assignMemberToTask(
            ['users' => [$this->leadModeller->uid], 'removed' => []],
            $task->uid
        );

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
        ]);
    });

    it('does not change the task status on a revise, but flags the pic as Revise', function () {
        $task = amtTask(status: TaskStatus::OnProgress->value);
        $emp = amtEmployee();

        amtService()->assignMemberToTask(
            ['users' => [$emp->uid], 'removed' => []],
            $task->uid,
            isRevise: true
        );

        // Whole status block is skipped when isRevise === true.
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::OnProgress->value,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $emp->id,
            'status' => TaskPicStatus::Revise->value,
        ]);
    });

    it('does not change the task status when needChangeTaskStatus is false', function () {
        $task = amtTask(status: null);
        $emp = amtEmployee();

        amtService()->assignMemberToTask(
            ['users' => [$emp->uid], 'removed' => []],
            $task->uid,
            needChangeTaskStatus: false
        );

        assertDatabaseHas('project_tasks', ['id' => $task->id, 'status' => null]);
        assertDatabaseHas('project_task_pics', ['project_task_id' => $task->id, 'employee_id' => $emp->id]);
    });

    it('nulls the task status when the last pic is removed', function () {
        $task = amtTask(status: TaskStatus::OnProgress->value);
        $emp = amtEmployee();
        ProjectTaskPic::create([
            'project_task_id' => $task->id,
            'employee_id' => $emp->id,
            'status' => TaskPicStatus::Approved->value,
        ]);

        $response = amtService()->assignMemberToTask(
            ['users' => [], 'removed' => [$emp->uid]],
            $task->uid
        );

        expect($response['error'])->toBeFalse();
        assertDatabaseHas('project_tasks', ['id' => $task->id, 'status' => null]);
        assertDatabaseMissing('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $emp->id,
        ]);
    });
});

describe('lead modeller auto-reclaim on emptying a task', function () {
    it('reclaims the task for the lead modeller when the lead modeller removes the last member', function () {
        // act as the configured lead modeller
        actingAs(User::where('employee_id', $this->leadModeller->id)->firstOrFail());

        $task = amtTask(status: TaskStatus::OnProgress->value);
        $member = amtEmployee();
        ProjectTaskPic::create([
            'project_task_id' => $task->id,
            'employee_id' => $member->id,
            'status' => TaskPicStatus::Approved->value,
        ]);

        $response = amtService()->assignMemberToTask(
            ['users' => [], 'removed' => [$member->uid]],
            $task->uid
        );

        expect($response['error'])->toBeFalse();

        // the removed member is gone
        assertDatabaseMissing('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $member->id,
        ]);

        // the lead modeller is auto-assigned as the sole pic and the task goes to WaitingDistribute
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $this->leadModeller->id,
            'status' => TaskPicStatus::WaitingToDistribute->value,
        ]);
        expect(ProjectTaskPic::where('project_task_id', $task->id)->pluck('employee_id')->all())
            ->toBe([$this->leadModeller->id]);
    });

    it('does not reclaim when the lead modeller removes a member but the task still has pics', function () {
        actingAs(User::where('employee_id', $this->leadModeller->id)->firstOrFail());

        $task = amtTask(status: TaskStatus::OnProgress->value);
        $memberA = amtEmployee();
        $memberB = amtEmployee();
        foreach ([$memberA, $memberB] as $member) {
            ProjectTaskPic::create([
                'project_task_id' => $task->id,
                'employee_id' => $member->id,
                'status' => TaskPicStatus::Approved->value,
            ]);
        }

        amtService()->assignMemberToTask(
            ['users' => [], 'removed' => [$memberA->uid]],
            $task->uid
        );

        // memberA removed, memberB stays, the lead modeller is NOT added, status unchanged
        assertDatabaseMissing('project_task_pics', ['project_task_id' => $task->id, 'employee_id' => $memberA->id]);
        assertDatabaseHas('project_task_pics', ['project_task_id' => $task->id, 'employee_id' => $memberB->id]);
        assertDatabaseMissing('project_task_pics', ['project_task_id' => $task->id, 'employee_id' => $this->leadModeller->id]);
        assertDatabaseHas('project_tasks', ['id' => $task->id, 'status' => TaskStatus::OnProgress->value]);
    });

    it('does not reclaim when a non-lead-modeller empties the task (stays null)', function () {
        // the beforeEach actor is a regular, non-lead-modeller user
        $task = amtTask(status: TaskStatus::OnProgress->value);
        $member = amtEmployee();
        ProjectTaskPic::create([
            'project_task_id' => $task->id,
            'employee_id' => $member->id,
            'status' => TaskPicStatus::Approved->value,
        ]);

        amtService()->assignMemberToTask(
            ['users' => [], 'removed' => [$member->uid]],
            $task->uid
        );

        assertDatabaseHas('project_tasks', ['id' => $task->id, 'status' => null]);
        assertDatabaseMissing('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $this->leadModeller->id,
        ]);
    });
});

describe('validation failures', function () {
    it('rejects assigning members while the task has no deadline', function () {
        $task = amtTask(status: null, endDate: null);
        $emp = amtEmployee();

        $response = amtService()->assignMemberToTask(['users' => [$emp->uid], 'removed' => []], $task->uid);

        expect($response['error'])->toBeTrue();
        assertDatabaseMissing('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $emp->id,
        ]);
        // status untouched
        assertDatabaseHas('project_tasks', ['id' => $task->id, 'status' => null]);
    });

    it('rejects combining the lead modeller with a regular member', function () {
        $task = amtTask(status: null);
        $emp = amtEmployee();

        $response = amtService()->assignMemberToTask(
            ['users' => [$this->leadModeller->uid, $emp->uid], 'removed' => []],
            $task->uid
        );

        expect($response['error'])->toBeTrue();
        assertDatabaseMissing('project_task_pics', ['project_task_id' => $task->id]);
    });
});
