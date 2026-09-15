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
use Modules\Production\Models\ProjectBoard;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Services\ProjectService;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * storeTask() creates a task on a board and, when a pic list is supplied, sets the initial
 * status: a lead-modeller pic yields WaitingDistribute (task) + WaitingToDistribute (pic),
 * any other pic yields WaitingApproval, and no pic leaves the status null.
 *
 * The detail pipeline (formattedDetailTask/FormatBoards/DetailCache) is stubbed; the task and
 * pic rows are written to the real DB. The lead modeller is matched by the 'lead_3d_modeller'
 * setting (getSettingByKey reads the 'setting' cache). loggingTask is a no-op under APP_ENV=testing.
 */
function stService(): ProjectService
{
    return app(ProjectService::class);
}

function stBoard(): ProjectBoard
{
    return ProjectBoard::factory()->create();
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

    // assignMemberToTask (called by storeTask) checks userData->hasPermissionTo('assign_modeller').
    Permission::firstOrCreate(['name' => 'assign_modeller', 'guard_name' => 'sanctum']);

    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail());

    $this->leadModeller = Employee::factory()->withUser()->create();
    Cache::forever('setting', [
        ['key' => 'lead_3d_modeller', 'value' => $this->leadModeller->uid],
    ]);
});

describe('storeTask lead modeller rule', function () {
    it('creates a task with the lead modeller pic as WaitingDistribute', function () {
        $board = stBoard();

        $response = stService()->storeTask([
            'name' => 'Modeling task',
            'pic' => [$this->leadModeller->uid],
            'end_date' => '2026-10-01 10:00:00',
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

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

    it('creates a task with a regular pic as WaitingApproval', function () {
        $board = stBoard();
        $member = Employee::factory()->withUser()->create();

        $response = stService()->storeTask([
            'name' => 'Compositing task',
            'pic' => [$member->uid],
            'end_date' => '2026-10-01 10:00:00',
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $member->id,
        ]);
    });

    it('creates a task without a pic and leaves the status null', function () {
        $board = stBoard();

        $response = stService()->storeTask([
            'name' => 'Unassigned task',
            'end_date' => '2026-10-01 10:00:00',
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => null,
        ]);
        assertDatabaseMissing('project_task_pics', ['project_task_id' => $task->id]);
    });
});
