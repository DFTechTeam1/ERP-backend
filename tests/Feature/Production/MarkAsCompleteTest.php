<?php

use App\Actions\PartialTaskPermissionCheck;
use App\Actions\Project\DetailCache;
use App\Actions\Project\Task\MarkAsComplete;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\Production\WorkType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\Hrd\Models\Employee;
use Modules\Production\Jobs\TaskIsCompleteJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectBoard;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Models\ProjectTaskPicApprovalstate;
use Modules\Production\Models\ProjectTaskPicWorkstate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * MarkAsComplete::handle() finalises a task. These tests run the flow-specific sub-actions
 * (ChangeTaskBoard, DetachTaskPic, SummarizeTaskTimeline, SaveTaskState) against the real DB and
 * assert their combined effect, while the two cross-cutting output collaborators are stubbed:
 *   - DetailCache: returns a known payload (we don't rebuild the heavy project cache here).
 *   - PartialTaskPermissionCheck: returns the loaded task unchanged (skips permission formatting).
 * TaskIsCompleteJob is intercepted with a fake queue. An authenticated user is required because
 * ChangeTaskBoard logs with Auth::user()->username.
 *
 * Both collaborators are Lorisleiva actions resolved from the container, so mocking the binding
 * intercepts their ::run() calls.
 */
beforeEach(function () {
    Queue::fake();

    $this->mock(DetailCache::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturn(['full_detail' => 'stub']);
    });
    $this->mock(PartialTaskPermissionCheck::class, function ($mock) {
        $mock->shouldReceive('handle')->andReturnUsing(fn ($taskDetail, $telegramEmployee = null) => $taskDetail);
    });

    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail());
});

/**
 * Build a task that is ready to be completed: a project with boards and a PIC, a worker
 * assigned as the current PIC with an open work state, and (for the normal path) an open
 * approval state.
 *
 * @return array{project: Project, task: ProjectTask, worker: Employee, pm: Employee, sourceBoardId: int}
 */
function macCompletableTask(bool $excluded = false): array
{
    $project = Project::factory()->withBoards()->create();

    $pm = Employee::factory()->create();
    ProjectPersonInCharge::create(['project_id' => $project->id, 'pic_id' => $pm->id]);

    $sourceBoardId = (int) ProjectBoard::where('project_id', $project->id)->get()->first()->id;

    $worker = Employee::factory()->create();

    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'project_board_id' => $sourceBoardId,
        'status' => TaskStatus::OnProgress->value,
        'current_pics' => json_encode([$worker->id]),
        'current_board' => null,
        'end_date' => '2026-10-01 10:00:00',
        'exclude_from_point_calculation' => $excluded,
    ]);

    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $worker->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => Carbon::now(),
        'approved_at' => Carbon::now(),
    ]);

    $workState = ProjectTaskPicWorkstate::create([
        'task_id' => $task->id,
        'employee_id' => $worker->id,
        'started_at' => Carbon::now(),
        'first_finish_at' => null,
        'complete_at' => null,
    ]);

    if (! $excluded) {
        ProjectTaskPicApprovalstate::create([
            'task_id' => $task->id,
            'pic_id' => $pm->id,
            'project_id' => $project->id,
            'work_state_id' => $workState->id,
            'started_at' => Carbon::now(),
            'approved_at' => null,
        ]);
    }

    return compact('project', 'task', 'worker', 'pm', 'sourceBoardId');
}

describe('normal completion', function () {
    it('completes the task, advances the board, closes states and notifies', function () {
        ['project' => $project, 'task' => $task, 'worker' => $worker, 'sourceBoardId' => $sourceBoardId] = macCompletableTask();

        // Replicate getNextBoardId() to know which board the task should land on.
        $boardList = ProjectBoard::where('project_id', $project->id)->get()->values();
        $sourceKey = $boardList->search(fn ($b) => $b->id == $sourceBoardId);
        $expectedBoardId = isset($boardList[$sourceKey + 1]) ? (int) $boardList[$sourceKey + 1]->id : $sourceBoardId;

        $result = MarkAsComplete::run($project->uid, $task->uid);

        expect($result)->toHaveKeys(['task', 'full_detail']);

        // Task finalised and moved to the next board, back in the pool with no deadline.
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Completed->value,
            'is_pool_task' => true,
            'end_date' => null,
            'project_board_id' => $expectedBoardId,
            'current_board' => $sourceBoardId,
        ]);

        // Finish working-time log stamped for the worker.
        assertDatabaseHas('project_task_pic_logs', [
            'project_task_id' => $task->id,
            'employee_id' => $worker->id,
            'work_type' => WorkType::Finish->value,
        ]);

        // PIC detached, but its (now closed) work state is kept in the normal path.
        assertDatabaseMissing('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $worker->id,
        ]);
        assertDatabaseMissing('project_task_pic_workstates', [
            'task_id' => $task->id,
            'employee_id' => $worker->id,
            'complete_at' => null,
        ]);

        // Open approval state closed.
        assertDatabaseMissing('project_task_pic_approvalstates', [
            'task_id' => $task->id,
            'approved_at' => null,
        ]);

        // Timeline summarised and employee state snapshot taken.
        assertDatabaseHas('project_task_duration_histories', [
            'task_id' => $task->id,
            'employee_id' => $worker->id,
            'task_type' => 'production',
        ]);
        assertDatabaseHas('employee_task_states', [
            'project_task_id' => $task->id,
            'employee_id' => $worker->id,
        ]);

        // Board move logged and completion notification dispatched.
        assertDatabaseHas('project_task_logs', [
            'project_task_id' => $task->id,
            'type' => 'moveTask',
        ]);
        Queue::assertPushed(TaskIsCompleteJob::class);
    });

    it('does not dispatch the completion job when notifications are suppressed', function () {
        ['project' => $project, 'task' => $task] = macCompletableTask();

        MarkAsComplete::run($project->uid, $task->uid, null, false);

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Completed->value,
        ]);
        Queue::assertNotPushed(TaskIsCompleteJob::class);
    });
});

describe('excluded task completion', function () {
    it('returns the task to the pool and detaches the pic without scoring or notifying', function () {
        ['project' => $project, 'task' => $task, 'worker' => $worker, 'sourceBoardId' => $sourceBoardId] = macCompletableTask(excluded: true);

        $result = MarkAsComplete::run($project->uid, $task->uid);

        expect($result)->toHaveKeys(['task', 'full_detail']);

        // Status reset to null, back in the pool, board NOT advanced.
        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => null,
            'is_pool_task' => true,
            'project_board_id' => $sourceBoardId,
            'current_board' => null,
        ]);

        // PIC detached together with its open work state and the current_pics entry.
        assertDatabaseMissing('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $worker->id,
        ]);
        assertDatabaseMissing('project_task_pic_workstates', [
            'task_id' => $task->id,
            'employee_id' => $worker->id,
        ]);
        // current_pics is a JSON column; compare the decoded value rather than a raw SQL string match.
        $task->refresh();
        expect(json_decode($task->current_pics, true))->toBe([]);

        // No scoring artefacts, no finish log and no notification on the excluded path.
        assertDatabaseCount('project_task_duration_histories', 0);
        assertDatabaseMissing('project_task_pic_logs', [
            'project_task_id' => $task->id,
            'work_type' => WorkType::Finish->value,
        ]);
        assertDatabaseMissing('project_task_logs', [
            'project_task_id' => $task->id,
            'type' => 'moveTask',
        ]);
        Queue::assertNotPushed(TaskIsCompleteJob::class);
    });
});
