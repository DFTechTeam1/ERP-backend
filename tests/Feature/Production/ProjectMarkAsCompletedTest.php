<?php

use App\Actions\PartialTaskPermissionCheck;
use App\Actions\Project\DetailCache;
use App\Actions\Project\Task\MarkAsComplete;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
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
use Modules\Production\Services\ProjectService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * ProjectService::markAsCompleted() is a thin transactional wrapper: it runs the MarkAsComplete
 * action inside a DB transaction, wraps its payload in a success generalResponse, and converts any
 * thrown error into an errorResponse after rolling back.
 *
 * The "function" tests mock MarkAsComplete so only the wrapper's own behaviour (response shape,
 * error handling) is exercised. The e2e test runs the real action end-to-end with only the heavy
 * output collaborators (DetailCache, PartialTaskPermissionCheck) and the queue stubbed.
 */
function pmacService(): ProjectService
{
    return app(ProjectService::class);
}

describe('markAsCompleted (function)', function () {
    it('wraps the action payload in a success response', function () {
        $payload = ['task' => 'formatted-task', 'full_detail' => 'detail-cache'];

        $this->mock(MarkAsComplete::class, function ($mock) use ($payload) {
            $mock->shouldReceive('handle')->once()->andReturn($payload);
        });

        $response = pmacService()->markAsCompleted('project-uid', 'task-uid');

        expect($response['error'])->toBeFalse();
        expect($response['message'])->toBe(__('global.taskIsCompletedAndContinue'));
        expect($response['data'])->toBe($payload);
    });

    it('returns an error response and rolls back when the action throws', function () {
        $this->mock(MarkAsComplete::class, function ($mock) {
            $mock->shouldReceive('handle')->once()->andThrow(new Exception('completion failed'));
        });

        $response = pmacService()->markAsCompleted('project-uid', 'task-uid');

        expect($response['error'])->toBeTrue();
    });
});

/**
 * Build a task ready for completion (project with boards + PIC, worker as current PIC with an
 * open work state and approval state). Named distinctly to avoid colliding with the identical
 * helper in MarkAsCompleteTest.php (top-level test functions share the global namespace).
 *
 * @return array{project: Project, task: ProjectTask, worker: Employee}
 */
function pmacCompletableTask(): array
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
        'exclude_from_point_calculation' => false,
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

    ProjectTaskPicApprovalstate::create([
        'task_id' => $task->id,
        'pic_id' => $pm->id,
        'project_id' => $project->id,
        'work_state_id' => $workState->id,
        'started_at' => Carbon::now(),
        'approved_at' => null,
    ]);

    return compact('project', 'task', 'worker');
}

describe('markAsCompleted (e2e)', function () {
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

    it('completes the task through the real action and returns a success response', function () {
        ['project' => $project, 'task' => $task, 'worker' => $worker] = pmacCompletableTask();

        $response = pmacService()->markAsCompleted($project->uid, $task->uid);

        expect($response['error'])->toBeFalse();
        expect($response['message'])->toBe(__('global.taskIsCompletedAndContinue'));
        expect($response['data'])->toHaveKeys(['task', 'full_detail']);

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Completed->value,
            'is_pool_task' => true,
            'end_date' => null,
        ]);
        assertDatabaseHas('employee_task_states', [
            'project_task_id' => $task->id,
            'employee_id' => $worker->id,
        ]);
        Queue::assertPushed(TaskIsCompleteJob::class);
    });
});
