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
 * storePoolTask() creates a task on a board. Without a pic it stays in the pool (is_pool_task
 * true, status null); with a pic the initial status follows the same rules as storeTask, except
 * that a task flagged exclude_from_point_calculation skips the approval flow and starts
 * OnProgress immediately. The lead-modeller distribution case is preserved.
 *
 * When a pic is supplied storePoolTask routes through mainStoreTask -> assignMemberToTask, so the
 * final status is the one that survives that path (assignMemberToTask leaves a non-null,
 * non-Completed status untouched, which keeps the OnProgress/WaitingApproval set here).
 *
 * The detail pipeline (formattedDetailTask/FormatBoards/DetailCache) is stubbed; task and pic rows
 * are written to the real DB. The lead modeller is matched by the 'lead_3d_modeller' setting.
 */
function sptService(): ProjectService
{
    return app(ProjectService::class);
}

function sptBoard(): ProjectBoard
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

    // assignMemberToTask (called via mainStoreTask) checks userData->hasPermissionTo('assign_modeller').
    Permission::firstOrCreate(['name' => 'assign_modeller', 'guard_name' => 'sanctum']);

    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail());

    $this->leadModeller = Employee::factory()->withUser()->create();
    Cache::forever('setting', [
        ['key' => 'lead_3d_modeller', 'value' => $this->leadModeller->uid],
    ]);
});

describe('storePoolTask exclude from point rule', function () {
    it('creates an excluded task with a pic as OnProgress', function () {
        $board = sptBoard();
        $member = Employee::factory()->withUser()->create();

        $response = sptService()->storePoolTask([
            'name' => 'Excluded compositing task',
            'pic' => [$member->uid],
            'end_date' => '2026-10-01 10:00:00',
            'exclude_from_point' => 1,
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::OnProgress->value,
            'is_pool_task' => false,
            'exclude_from_point_calculation' => true,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $member->id,
        ]);
    });

    it('creates a non-excluded task with a pic as WaitingApproval', function () {
        $board = sptBoard();
        $member = Employee::factory()->withUser()->create();

        $response = sptService()->storePoolTask([
            'name' => 'Regular compositing task',
            'pic' => [$member->uid],
            'end_date' => '2026-10-01 10:00:00',
            'exclude_from_point' => 0,
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingApproval->value,
            'is_pool_task' => false,
            'exclude_from_point_calculation' => false,
        ]);
    });

    it('leaves an excluded task without a pic in the pool with a null status', function () {
        $board = sptBoard();

        $response = sptService()->storePoolTask([
            'name' => 'Excluded pool task',
            'exclude_from_point' => 1,
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => null,
            'is_pool_task' => true,
            'exclude_from_point_calculation' => true,
        ]);
        assertDatabaseMissing('project_task_pics', ['project_task_id' => $task->id]);
    });

    it('keeps the lead modeller distribution case even when excluded', function () {
        $board = sptBoard();

        $response = sptService()->storePoolTask([
            'name' => 'Excluded modeling task',
            'pic' => [$this->leadModeller->uid],
            'end_date' => '2026-10-01 10:00:00',
            'exclude_from_point' => 1,
        ], $board->id);

        expect($response['error'])->toBeFalse();

        $task = ProjectTask::where('project_board_id', $board->id)->firstOrFail();

        assertDatabaseHas('project_tasks', [
            'id' => $task->id,
            'status' => TaskStatus::WaitingDistribute->value,
            'exclude_from_point_calculation' => true,
        ]);
        assertDatabaseHas('project_task_pics', [
            'project_task_id' => $task->id,
            'employee_id' => $this->leadModeller->id,
            'status' => TaskPicStatus::WaitingToDistribute->value,
        ]);
    });
});
