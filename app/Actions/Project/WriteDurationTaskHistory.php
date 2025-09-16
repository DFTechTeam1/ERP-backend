<?php

namespace App\Actions\Project;

use App\Enums\Production\TaskHistoryType;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectTaskPicLogRepository;

class WriteDurationTaskHistory
{
    use AsAction;

    public function handle(int $projectTaskId)
    {
        // get duration first
        $projectTaskPicLogRepo = new ProjectTaskPicLogRepository;

        $tasks = $projectTaskPicLogRepo->list(
            select: 'id,employee_id,project_task_id,time_added,work_type',
            where: "project_task_id = {$projectTaskId}",
            orderBy: 'time_added ASC',
            relation: [
                'task:id,project_id',
                'task.project:id',
                'task.project.personInCharges',
                'employee:id,name',
            ]
        );

        $pm = collect($tasks[0]->task->project->personInCharges)->pluck('pic_id')->toArray();

        $output = $tasks->filter(function ($filter) use ($pm) {
            return ! in_array($filter->employee_id, $pm);
        })->values()->map(function ($mapping) {
            return [
                'id' => $mapping->id,
                'employee_id' => $mapping->employee_id,
                'project_task_id' => $mapping->project_task_id,
                'time_added' => $mapping->time_added,
                'work_type' => $mapping->work_type,
                'employee' => $mapping->employee->name,
                'project_id' => $mapping->task->project_id,
            ];
        })->groupBy('employee_id')->toArray();

        // format output to be filled to the new table
        $payload = [];
        foreach ($output as $employeeId => $dataGroup) {
            $start = Carbon::parse($dataGroup[0]['time_added']);
            $end = Carbon::parse($dataGroup[count($dataGroup) - 1]['time_added']);
            $duration = $start->diffInSeconds($end);

            $payload[] = [
                'project_id' => $dataGroup[0]['project_id'],
                'task_id' => $dataGroup[0]['project_task_id'],
                'pic_id' => $employeeId,
                'task_duration' => $duration,
                'pm_approval_duration' => null,
                'task_type' => TaskHistoryType::SingleAssignee,
                'created_at' => Carbon::now(),
            ];
        }

        return $payload;
    }
}
