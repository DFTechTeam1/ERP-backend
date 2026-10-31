<?php

use App\Actions\Project\Task\DetachTaskPic;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPicHistory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * DetachTaskPic::handle() removes one or more PICs from a task. The task_pics row is always
 * deleted; the history row, the open work state and the task's current_pics entry are only
 * touched when their respective flags ($removeFromHistory, $removeWorkState, $removeCurrentPic)
 * are on, and a 'assignMemberTask' log is written unless $doLogging is false. When $message is
 * supplied it overrides the default "removed member" log text.
 *
 * ids are employee uids by default ($isEmployeeUid = true), resolved via getIdFromUid().
 */
beforeEach(function () {
    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail());
});

it('removes the pic, history, work state and current_pics entry and logs the detach', function () {
    $worker = Employee::factory()->create();
    $task = ProjectTask::factory()
        ->withPics($worker, withWorkState: true, withCurrentPic: true)
        ->create();

    ProjectTaskPicHistory::create([
        'project_id' => $task->project_id,
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    DetachTaskPic::run(
        ids: [$worker->uid],
        taskId: $task->id,
        isEmployeeUid: true,
        removeFromHistory: true,
    );

    assertDatabaseMissing('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
    assertDatabaseMissing('project_task_pic_histories', [
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
    assertDatabaseMissing('project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'complete_at' => null,
    ]);
    // current_pics is a JSON column; compare the decoded value rather than a raw SQL string match.
    $task->refresh();
    expect(json_decode($task->current_pics, true))->toBe([]);
    assertDatabaseHas('project_task_logs', [
        'project_task_id' => $task->id,
        'type' => 'assignMemberTask',
    ]);
});

it('keeps history, work state and current_pics and skips logging when those flags are off', function () {
    $worker = Employee::factory()->create();
    $task = ProjectTask::factory()
        ->withPics($worker, withWorkState: true, withCurrentPic: true)
        ->create();

    ProjectTaskPicHistory::create([
        'project_id' => $task->project_id,
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);

    DetachTaskPic::run(
        ids: [$worker->uid],
        taskId: $task->id,
        isEmployeeUid: true,
        removeFromHistory: false,
        message: '',
        doLogging: false,
        removeWorkState: false,
        removeCurrentPic: false,
    );

    // The task_pics row is always removed regardless of the flags.
    assertDatabaseMissing('project_task_pics', [
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
    assertDatabaseHas('project_task_pic_histories', [
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
    ]);
    assertDatabaseHas('project_task_pic_workstates', [
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'complete_at' => null,
    ]);
    // current_pics is a JSON column; compare the decoded value rather than a raw SQL string match.
    $task->refresh();
    expect(json_decode($task->current_pics, true))->toBe([$worker->id]);
    assertDatabaseMissing('project_task_logs', [
        'project_task_id' => $task->id,
        'type' => 'assignMemberTask',
    ]);
});

it('uses the supplied message as the log text', function () {
    $worker = Employee::factory()->create();
    $task = ProjectTask::factory()->withPics($worker)->create();

    DetachTaskPic::run(
        ids: [$worker->uid],
        taskId: $task->id,
        isEmployeeUid: true,
        message: 'Removed because the task was reassigned',
    );

    assertDatabaseHas('project_task_logs', [
        'project_task_id' => $task->id,
        'type' => 'assignMemberTask',
        'text' => 'Removed because the task was reassigned',
    ]);
});
