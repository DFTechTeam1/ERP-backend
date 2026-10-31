<?php

use App\Actions\Project\SaveTaskState;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * SaveTaskState::handle() snapshots the employee task state for each supplied PIC id, keyed by
 * project, task, board and employee. It uses updateOrCreate, so re-running it for the same PICs
 * never produces duplicate rows.
 */
it('stores one employee task state per current PIC', function () {
    $task = ProjectTask::factory()->create();
    $empOne = Employee::factory()->create();
    $empTwo = Employee::factory()->create();

    SaveTaskState::run([$empOne->id, $empTwo->id], $task->uid);

    assertDatabaseCount('employee_task_states', 2);
    foreach ([$empOne, $empTwo] as $emp) {
        assertDatabaseHas('employee_task_states', [
            'project_id' => $task->project_id,
            'project_task_id' => $task->id,
            'project_board_id' => $task->project_board_id,
            'employee_id' => $emp->id,
        ]);
    }
});

it('does not duplicate state rows when run again for the same PICs', function () {
    $task = ProjectTask::factory()->create();
    $emp = Employee::factory()->create();

    SaveTaskState::run([$emp->id], $task->uid);
    SaveTaskState::run([$emp->id], $task->uid);

    assertDatabaseCount('employee_task_states', 1);
});
