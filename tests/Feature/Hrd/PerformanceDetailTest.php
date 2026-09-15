<?php

use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmployeePoint;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Services\PerformanceReportService;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPicLog;

/**
 * GET /api/performanceReport/{employeeUid} -> PerformanceReportService::performanceDetail().
 *
 * The whole screen is period-scoped (the period selector drives it), so the "Realtime Work
 * Summary" chart must reflect the SAME period as the points/projects. Previously the chart was
 * built from every pic log the employee ever had (list('*', 'employee_id = X') with no date
 * filter), so it showed all-time totals even when the period had no projects at all - the
 * "0 total point / no projects / donut shows a huge number" mismatch. These tests lock the
 * chart to the selected period.
 */
function perfDetailService(): PerformanceReportService
{
    return app(PerformanceReportService::class);
}

function perfDetailLog(Project $project, Employee $employee, string $workType): void
{
    $task = ProjectTask::factory()->create(['project_id' => $project->id]);
    ProjectTaskPicLog::create([
        'project_task_id' => $task->id,
        'employee_id' => $employee->id,
        'work_type' => $workType,
        'time_added' => now(),
    ]);
}

beforeEach(function () {
    request()->merge(['start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
});

it('scopes the realtime work summary chart to the selected period', function () {
    $employee = Employee::factory()->create();

    $inPeriod = Project::factory()->create(['project_date' => '2026-08-15']);
    $outOfPeriod = Project::factory()->create(['project_date' => '2026-03-15']);

    perfDetailLog($inPeriod, $employee, 'finish');    // counts
    perfDetailLog($outOfPeriod, $employee, 'finish'); // must be excluded

    $response = perfDetailService()->performanceDetail($employee->uid);

    expect($response['error'])->toBeFalse();

    // series = [completed(finish), revise, waiting(assigned), progress(on_progress)]
    $series = $response['data']['chart']['series'];
    expect($series[0])->toBe(1)                 // only the in-period finish
        ->and(array_sum($series))->toBe(1)      // the out-of-period log is not counted
        ->and($response['data']['chart']['show_chart'])->toBeTrue();
});

it('returns a zeroed work summary and no projects when the period has no data', function () {
    $employee = Employee::factory()->create();

    // work only OUTSIDE the selected period
    $outOfPeriod = Project::factory()->create(['project_date' => '2026-03-15']);
    perfDetailLog($outOfPeriod, $employee, 'finish');
    perfDetailLog($outOfPeriod, $employee, 'assigned');

    $response = perfDetailService()->performanceDetail($employee->uid);

    expect($response['error'])->toBeFalse()
        ->and($response['data']['chart']['series'])->toBe([0, 0, 0, 0])
        ->and($response['data']['chart']['show_chart'])->toBeFalse()
        ->and($response['data']['total_project'])->toBe(0)
        ->and($response['data']['realtime_report']['total_point'])->toBe(0)
        ->and($response['data']['point_detail'])->toBe([]);
});

it('reports the period point total and project count from in-period point projects', function () {
    $employee = Employee::factory()->create();
    $project = Project::factory()->create(['project_date' => '2026-08-20']);

    $point = EmployeePoint::create([
        'employee_id' => $employee->id,
        'total_point' => 5,
        'type' => 'production',
    ]);
    EmployeePointProject::create([
        'employee_point_id' => $point->id,
        'project_id' => $project->id,
        'total_point' => 5,
        'additional_point' => 2,
        'prorate_point' => 0,
        'calculated_prorate_point' => 0,
        'original_point' => 3,
    ]);

    $response = perfDetailService()->performanceDetail($employee->uid);

    expect($response['error'])->toBeFalse()
        ->and($response['data']['realtime_report']['total_point'])->toBe(5)
        ->and($response['data']['total_project'])->toBe(1);
});
