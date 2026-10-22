<?php

use App\Actions\Hrd\PointRecord;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Hrd\Services\EmployeePointService;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Models\ProjectTask;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Permanent fix for the performance-report task mismatch: a point detail's task_id
 * is a polymorphic reference to two tables with overlapping ids (project_tasks vs
 * entertainment_task_songs). The new task_type discriminator resolves it correctly
 * regardless of the (previously stale) employee_points.type, and production/entertainment
 * points no longer collapse into one employee_point row.
 */

/**
 * Create a production ProjectTask and an entertainment EntertainmentTaskSong that
 * share the SAME id in the SAME project — the exact ambiguity that caused the mismatch.
 *
 * @return array{project: Project, taskId: int, songName: string}
 */
function overlappingTasks(int $employeeId, string $songName = 'My Song'): array
{
    $prodTask = ProjectTask::factory()->create(['name' => 'Production Asset']);
    $project = $prodTask->project;

    $song = ProjectSongList::factory()->create(['project_id' => $project->id, 'name' => $songName, 'created_by' => 1]);

    $ets = EntertainmentTaskSong::factory()->make([
        'project_id' => $project->id,
        'project_song_list_id' => $song->id,
        'employee_id' => $employeeId,
    ]);
    $ets->id = $prodTask->id; // force the overlap
    $ets->save();

    return ['project' => $project, 'taskId' => $prodTask->id, 'songName' => $songName];
}

function makePointChain(int $employeeId, int $projectId, string $type, int $taskId, ?string $taskType): EmployeePointProjectDetail
{
    $ep = EmployeePoint::create(['employee_id' => $employeeId, 'total_point' => 1, 'type' => $type]);
    $pp = EmployeePointProject::create([
        'employee_point_id' => $ep->id,
        'project_id' => $projectId,
        'total_point' => 1,
        'additional_point' => 0,
        'original_point' => 1,
    ]);

    return EmployeePointProjectDetail::create([
        'point_id' => $pp->id,
        'task_id' => $taskId,
        'task_type' => $taskType,
    ]);
}

describe('detail resolution by task_type', function () {
    it('resolves the SAME task_id to different tables based on task_type', function () {
        $employee = Employee::factory()->create();
        ['project' => $project, 'taskId' => $taskId, 'songName' => $songName] = overlappingTasks($employee->id, 'Bohemian Rhapsody');

        $entDetail = makePointChain($employee->id, $project->id, 'entertainment', $taskId, 'entertainment');
        $prodDetail = makePointChain($employee->id, $project->id, 'production', $taskId, 'production');

        expect($entDetail->fresh()->resolveTaskName($project->id))->toBe($songName)
            ->and($prodDetail->fresh()->resolveTaskName($project->id))->toBe('Production Asset');
    });

    it('falls back to the project-scoped match when task_type is null (not yet backfilled)', function () {
        $employee = Employee::factory()->create();
        $prodTask = ProjectTask::factory()->create(['name' => 'Only Production']);

        $detail = makePointChain($employee->id, $prodTask->project_id, 'production', $prodTask->id, null);

        expect($detail->fresh()->resolveTaskName($prodTask->project_id))->toBe('Only Production');
    });
});

describe('PointRecord keeps production and entertainment separate', function () {
    it('creates a distinct entertainment employee_point instead of reusing the production one', function () {
        $employee = Employee::factory()->create();
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->create(['project_id' => $project->id]);

        // Employee already has a production point record (pre-move history).
        EmployeePoint::create(['employee_id' => $employee->id, 'total_point' => 0, 'type' => 'production']);

        (new PointRecord)->handle(
            ['points' => [['uid' => $employee->uid, 'point' => 1, 'additional_point' => 0, 'tasks' => [$task->id]]]],
            $project->uid,
            'entertainment',
        );

        expect(EmployeePoint::where('employee_id', $employee->id)->count())->toBe(2)
            ->and(EmployeePoint::where('employee_id', $employee->id)->where('type', 'entertainment')->exists())->toBeTrue();

        $entPoint = EmployeePoint::where('employee_id', $employee->id)->where('type', 'entertainment')->first();
        $pp = EmployeePointProject::where('employee_point_id', $entPoint->id)->first();

        assertDatabaseHas('employee_point_project_details', [
            'point_id' => $pp->id,
            'task_id' => $task->id,
            'task_type' => 'entertainment',
        ]);
    });
});

describe('renderEachEmployeePoint across both point rows', function () {
    it('resolves each project\'s tasks to the correct table for a moved employee', function () {
        $employee = Employee::factory()->create();

        // Entertainment work: a song whose id overlaps a production task id.
        ['project' => $entProject, 'taskId' => $overlapId, 'songName' => $songName] = overlappingTasks($employee->id, 'A Thousand Years');
        $entProject->update(['project_date' => '2026-06-15']);
        makePointChain($employee->id, $entProject->id, 'entertainment', $overlapId, 'entertainment');

        // Production work: a plain production task in another project.
        $prodTask = ProjectTask::factory()->create(['name' => 'LED Asset']);
        $prodTask->project->update(['project_date' => '2026-06-16']);
        makePointChain($employee->id, $prodTask->project_id, 'production', $prodTask->id, 'production');

        $result = app(EmployeePointService::class)->renderEachEmployeePoint($employee->id, '2026-06-01', '2026-06-30');

        $names = collect($result['task_details'])->flatMap(fn ($d) => collect($d['tasks'])->pluck('name'))->all();

        expect($result['total_project'])->toBe(2)
            ->and($names)->toContain($songName)
            ->and($names)->toContain('LED Asset')
            ->and($names)->not->toContain('Production Asset'); // the overlapping prod task must NOT leak in
    });
});

describe('backfill command', function () {
    it('types existing null rows from the project-scoped match', function () {
        $employee = Employee::factory()->create();

        $prodTask = ProjectTask::factory()->create(['name' => 'Prod']);
        $prodDetail = makePointChain($employee->id, $prodTask->project_id, 'production', $prodTask->id, null);

        $song = ProjectSongList::factory()->create(['project_id' => $prodTask->project_id, 'name' => 'Song', 'created_by' => 1]);
        $ets = EntertainmentTaskSong::factory()->make([
            'project_id' => $prodTask->project_id,
            'project_song_list_id' => $song->id,
            'employee_id' => $employee->id,
        ]);
        $ets->save();
        $entDetail = makePointChain($employee->id, $prodTask->project_id, 'entertainment', $ets->id, null);

        artisan('hrd:backfill-point-task-type', ['--apply' => true])->assertSuccessful();

        assertDatabaseHas('employee_point_project_details', ['id' => $prodDetail->id, 'task_type' => 'production']);
        assertDatabaseHas('employee_point_project_details', ['id' => $entDetail->id, 'task_type' => 'entertainment']);
    });

    it('does not write anything in dry-run mode', function () {
        $employee = Employee::factory()->create();
        $prodTask = ProjectTask::factory()->create(['name' => 'Prod']);
        $detail = makePointChain($employee->id, $prodTask->project_id, 'production', $prodTask->id, null);

        artisan('hrd:backfill-point-task-type')->assertSuccessful();

        expect($detail->fresh()->task_type)->toBeNull();
    });
});
