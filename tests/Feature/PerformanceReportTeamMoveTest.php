<?php

use App\Enums\Production\TaskSongStatus;
use App\Exports\NewTemplatePerformanceReportExport;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Models\ProjectTask;

/*
 * Regression for an employee who moved between the production and entertainment
 * teams. employee_points.type is a single value per employee and is never
 * updated, so it stays 'production' after the move. A detail's task_id is looked
 * up against two tables (project_tasks vs entertainment_task_songs) whose ids
 * overlap, so the stale type made the report resolve an in-range entertainment
 * detail as a production task from an unrelated, out-of-range project.
 *
 * The export must resolve each task against the point-project's own project,
 * independent of employee_points.type.
 */
it('does not leak an out-of-range production task for a former production employee now doing entertainment work', function () {
    // Out-of-range project (like DF 284, Feb 2025) that owns a production task.
    $outOfRangeProject = Project::factory()->create([
        'name' => 'Feb Project 284',
        'project_date' => '2025-02-15',
    ]);

    // In-range entertainment project the report is actually asking about.
    $inRangeProject = Project::factory()->create([
        'name' => 'Aug Project',
        'project_date' => '2026-08-23',
    ]);

    $employee = Employee::factory()->create(['name' => 'Ervin']);

    // A real production task on the OUT-OF-RANGE project. Its auto id is the id
    // we will collide with, so a production-typed lookup resolves to this row.
    $productionTask = ProjectTask::factory()->create([
        'project_id' => $outOfRangeProject->id,
        'name' => 'Feb Production Task',
    ]);

    $song = ProjectSongList::factory()->create([
        'project_id' => $inRangeProject->id,
        'name' => 'Aug Song',
    ]);

    // Entertainment task on the IN-RANGE project, forced to share the production
    // task's id to reproduce the cross-table collision.
    $entertainmentTask = EntertainmentTaskSong::forceCreate([
        'id' => $productionTask->id,
        'project_song_list_id' => $song->id,
        'employee_id' => $employee->id,
        'project_id' => $inRangeProject->id,
        'status' => TaskSongStatus::Active->value,
    ]);

    // The employee's single point row still carries the stale 'production' type.
    $point = EmployeePoint::create([
        'employee_id' => $employee->id,
        'total_point' => 5,
        'type' => 'production',
    ]);

    $pointProject = EmployeePointProject::create([
        'employee_point_id' => $point->id,
        'project_id' => $inRangeProject->id,
        'total_point' => 5,
        'additional_point' => 0,
        'prorate_point' => 0,
        'original_point' => 5,
        'calculated_prorate_point' => 0,
    ]);

    EmployeePointProjectDetail::create([
        'point_id' => $pointProject->id,
        'task_id' => $entertainmentTask->id,
    ]);

    $points = (new NewTemplatePerformanceReportExport('2026-08-23', '2026-08-24', 1, 'x'))
        ->view()
        ->getData()['points'];

    // The point-project row for the in-range project must show the entertainment
    // song, not the collided out-of-range production task.
    $mainRow = collect($points['Aug Project'] ?? [])->firstWhere('total_point', 5);
    expect($mainRow)->not->toBeNull();
    expect($mainRow['tasks'])->toBe('Aug Song');

    // The out-of-range production task must never appear anywhere in the report.
    $allTasks = collect($points)->flatMap(fn ($rows) => collect($rows)->pluck('tasks'))->all();
    foreach ($allTasks as $tasks) {
        expect($tasks)->not->toContain('Feb Production Task');
    }
});
