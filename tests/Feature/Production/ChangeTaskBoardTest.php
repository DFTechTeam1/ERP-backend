<?php

use App\Actions\Project\Task\ChangeTaskBoard;
use App\Enums\Production\TaskStatus;
use App\Models\User;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * ChangeTaskBoard::handle() moves a task from a source board onto a target board. It always
 * writes project_board_id (= board_id) and current_board (= board_source_id) and logs a
 * 'moveTask' entry. Optionally it also sets the task status (when $nextTaskStatus is given)
 * and refreshes current_pics from the task's PIC rows (when $setCurrentPic is true).
 *
 * writeLog() reads Auth::user()->username and Auth::id(), so an authenticated user is required.
 * The source and target board names come from the project's boards (factory-generated).
 */
beforeEach(function () {
    $actor = Employee::factory()->withUser()->create();
    actingAs(User::where('employee_id', $actor->id)->firstOrFail());
});

it('moves the task to the target board, sets status and current pics, and logs the move', function () {
    $worker = Employee::factory()->create();
    $task = ProjectTask::factory()->withPics($worker)->create(['status' => TaskStatus::OnProgress->value]);

    $boards = $task->project->boards()->orderBy('id')->get();
    $source = $boards->firstWhere('id', $task->project_board_id);
    $target = $boards->where('id', '!=', $source->id)->first();

    ChangeTaskBoard::run([
        'task_id' => $task->id,
        'board_id' => $target->id,
        'board_source_id' => $source->id,
    ], TaskStatus::CheckByPm->value, true);

    assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'project_board_id' => $target->id,
        'current_board' => $source->id,
        'status' => TaskStatus::CheckByPm->value,
    ]);
    // current_pics is a JSON column; compare the decoded value rather than a raw SQL string match.
    $task->refresh();
    expect(json_decode($task->current_pics, true))->toBe([$worker->id]);
    assertDatabaseHas('project_task_logs', [
        'project_task_id' => $task->id,
        'type' => 'moveTask',
    ]);
});

it('leaves status and current_pics untouched when those flags are not passed', function () {
    $task = ProjectTask::factory()->withPics()->create([
        'status' => TaskStatus::OnProgress->value,
        'current_pics' => null,
    ]);

    $boards = $task->project->boards()->orderBy('id')->get();
    $source = $boards->firstWhere('id', $task->project_board_id);
    $target = $boards->where('id', '!=', $source->id)->first();

    ChangeTaskBoard::run([
        'task_id' => $task->id,
        'board_id' => $target->id,
        'board_source_id' => $source->id,
    ]);

    assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'project_board_id' => $target->id,
        'current_board' => $source->id,
        'status' => TaskStatus::OnProgress->value,
        'current_pics' => null,
    ]);
});
