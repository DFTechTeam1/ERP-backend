<?php

namespace Modules\Hrd\Console;

use App\Enums\Hrd\PointTaskType;
use Illuminate\Console\Command;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Hrd\Models\EmployeePointProjectDetail;
use Modules\Production\Models\EntertainmentTaskSong;
use Modules\Production\Models\ProjectTask;

/**
 * Backfill employee_point_project_details.task_type for rows created before the
 * discriminator existed. A detail's task_id is resolved against the task tables
 * scoped to its own project:
 *   - only project_tasks matches  -> production
 *   - only entertainment songs match -> entertainment
 *   - both match (overlapping id in the same project) -> the song the employee
 *     actually performed wins, else the parent employee_point.type
 *   - neither matches in the project -> the parent employee_point.type (logged)
 *
 * Dry-run by default: prints the tally and leaves data untouched. Pass --apply to write.
 */
class BackfillPointTaskType extends Command
{
    protected $signature = 'hrd:backfill-point-task-type {--apply : Write task_type (default only reports)}';

    protected $description = 'Backfill task_type on employee_point_project_details so overlapping task ids resolve to the right table';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLY mode — writing task_type.' : 'DRY-RUN — no data will change. Pass --apply to write.');

        $tally = ['production' => 0, 'entertainment' => 0, 'ambiguous' => 0, 'fallback_parent' => 0, 'unresolved' => 0];
        /** @var array<string, array<int,int>> $toWrite  task_type => [detail ids] */
        $toWrite = [PointTaskType::Production->value => [], PointTaskType::Entertainment->value => []];

        EmployeePointProject::query()
            ->select('id', 'project_id', 'employee_point_id')
            ->with('employeePoint:id,type,employee_id')
            ->whereHas('details', fn ($q) => $q->whereNull('task_type'))
            ->chunkById(200, function ($pointProjects) use (&$tally, &$toWrite) {
                foreach ($pointProjects as $pp) {
                    $projectId = (int) $pp->project_id;
                    $parentType = $pp->employeePoint?->type;
                    $employeeId = $pp->employeePoint?->employee_id;

                    $details = EmployeePointProjectDetail::select('id', 'task_id')
                        ->where('point_id', $pp->id)
                        ->whereNull('task_type')
                        ->get();
                    $taskIds = $details->pluck('task_id')->unique()->all();
                    if (empty($taskIds)) {
                        continue;
                    }

                    $prodIds = ProjectTask::whereIn('id', $taskIds)->where('project_id', $projectId)->pluck('id')->all();
                    $entIds = EntertainmentTaskSong::whereIn('id', $taskIds)->where('project_id', $projectId)->pluck('id')->all();
                    $entByEmployee = $employeeId
                        ? EntertainmentTaskSong::whereIn('id', $taskIds)->where('project_id', $projectId)->where('employee_id', $employeeId)->pluck('id')->all()
                        : [];

                    foreach ($details as $detail) {
                        $taskId = (int) $detail->task_id;
                        $inProd = in_array($taskId, $prodIds);
                        $inEnt = in_array($taskId, $entIds);

                        if ($inProd && ! $inEnt) {
                            $type = PointTaskType::Production->value;
                            $tally['production']++;
                        } elseif ($inEnt && ! $inProd) {
                            $type = PointTaskType::Entertainment->value;
                            $tally['entertainment']++;
                        } elseif ($inProd && $inEnt) {
                            $type = in_array($taskId, $entByEmployee)
                                ? PointTaskType::Entertainment->value
                                : ($parentType ?: PointTaskType::Production->value);
                            $tally['ambiguous']++;
                        } else {
                            if (! $parentType) {
                                $tally['unresolved']++;

                                continue;
                            }
                            $type = $parentType;
                            $tally['fallback_parent']++;
                        }

                        $toWrite[$type][] = $detail->id;
                    }
                }
            });

        if ($apply) {
            foreach ($toWrite as $type => $ids) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    EmployeePointProjectDetail::whereIn('id', $chunk)->update(['task_type' => $type]);
                }
            }
        }

        $this->table(
            ['Resolution', 'Rows'],
            [
                ['production (unambiguous)', $tally['production']],
                ['entertainment (unambiguous)', $tally['entertainment']],
                ['ambiguous (both in project, tie-broken)', $tally['ambiguous']],
                ['fallback to parent type (not in project)', $tally['fallback_parent']],
                ['unresolved (left null)', $tally['unresolved']],
                ['TOTAL to write', count($toWrite[PointTaskType::Production->value]) + count($toWrite[PointTaskType::Entertainment->value])],
            ]
        );

        if (! $apply) {
            $this->warn('Dry-run only. Re-run with --apply once the tally looks right.');
        } else {
            $this->info('Done. task_type written.');
        }

        return self::SUCCESS;
    }
}
