<?php

namespace App\Schedules;

use App\Enums\Production\TaskStatus;
use App\Jobs\UpcomingDeadlineTaskJob;
use Modules\Production\Models\ProjectTask;

class UpcomingDeadlineTask {
    public function __invoke()
    {
        $endDate = date('Y-m-d', strtotime('+2 days'));

        $tasks = ProjectTask::selectRaw('id,uid,project_id,name')
            ->with([
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,nickname,email,line_id,telegram_chat_id',
                'project:id,name'
            ])
            ->whereIn(
                'status',
                [
                    TaskStatus::WaitingApproval->value,
                    TaskStatus::OnProgress->value,
                    TaskStatus::Revise->value,
                ]
            )
            ->where('end_date', $endDate)
            ->get();

        $outputData = [];
        foreach ($tasks as $task) {
            foreach ($task->pics as $employee) {
                $outputData[] = [
                    'employee' => $employee,
                    'task' => $task,
                ];
            }
        }

        UpcomingDeadlineTaskJob::dispatch($outputData);
    }
}
