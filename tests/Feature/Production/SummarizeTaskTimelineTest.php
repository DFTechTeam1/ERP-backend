<?php

use App\Actions\Production\SummarizeTaskTimeline;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectTask;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * SummarizeTaskTimeline::handle() persists a production duration summary for a completed task.
 * It writes one project_task_duration_histories row for every (project PIC x current PIC)
 * pair. With no work states recorded, WorkDurationService::buildTaskDuration() falls back to
 * zero durations, which is the shape asserted here.
 */
it('writes one duration-history row per project PIC and current PIC pair', function () {
    $task = ProjectTask::factory()->create();

    $pm = Employee::factory()->create();
    ProjectPersonInCharge::create([
        'project_id' => $task->project_id,
        'pic_id' => $pm->id,
    ]);

    $worker = Employee::factory()->create();

    SummarizeTaskTimeline::run($task->uid, [$worker->id]);

    assertDatabaseCount('project_task_duration_histories', 1);
    assertDatabaseHas('project_task_duration_histories', [
        'project_id' => $task->project_id,
        'task_id' => $task->id,
        'pic_id' => $pm->id,
        'task_type' => 'production',
        'employee_id' => $worker->id,
        'task_full_duration' => 0,
        'task_actual_duration' => 0,
        'total_task_holded' => 0,
        'total_task_revised' => 0,
        'is_interactive' => false,
    ]);
});

it('expands over every project PIC and every current PIC', function () {
    $task = ProjectTask::factory()->create();

    $pmOne = Employee::factory()->create();
    $pmTwo = Employee::factory()->create();
    foreach ([$pmOne, $pmTwo] as $pm) {
        ProjectPersonInCharge::create([
            'project_id' => $task->project_id,
            'pic_id' => $pm->id,
        ]);
    }

    $workerOne = Employee::factory()->create();
    $workerTwo = Employee::factory()->create();

    SummarizeTaskTimeline::run($task->uid, [$workerOne->id, $workerTwo->id]);

    // 2 project PICs x 2 current PICs = 4 rows.
    assertDatabaseCount('project_task_duration_histories', 4);
    assertDatabaseHas('project_task_duration_histories', [
        'task_id' => $task->id,
        'pic_id' => $pmTwo->id,
        'employee_id' => $workerTwo->id,
    ]);
});
