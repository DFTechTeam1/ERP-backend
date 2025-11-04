<?php

namespace App\Actions\Production;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Services\WorkDurationService;

class SummarizeTaskTimeline
{
    use AsAction;

    /**
     * Record timeline summary based on current states
     */
    public function handle(string $taskUid, array $currentPics): void
    {
        $task = (new ProjectTaskRepository)->show(
            uid: $taskUid,
            select: 'id,project_id,current_pics',
            relation: [
                'pics:id,project_task_id,employee_id',
                'holdStates:id,task_id,holded_at,unholded_at,work_state_id',
                'workStates:id,task_id,started_at,first_finish_at,employee_id',
                'reviseStates:id,task_id,start_at,finish_at,work_state_id',
                'project:id',
                'project.personInCharges:id,project_id,pic_id',
                'approvalStates:id,task_id,started_at,approved_at,work_state_id',
            ]
        );

        [$holdDuration, $reviseDuration, $workStateDuration, $actualDuration, $approvalStateDuration, $fullDuration, $totalHold, $totalRevise] = (new WorkDurationService)->buildTaskDuration(task: $task);

        $payload = [];

        foreach ($task->project->personInCharges as $pic) {
            foreach ($currentPics as $taskPic) {
                $payload[] = [
                    'project_id' => $task->project_id,
                    'task_id' => $task->id,
                    'pic_id' => $pic->pic_id,
                    'task_type' => 'production',
                    'employee_id' => $taskPic,
                    'task_full_duration' => $fullDuration,
                    'task_holded_duration' => $holdDuration,
                    'task_revised_duration' => $reviseDuration,
                    'task_actual_duration' => $actualDuration,
                    'task_approval_duration' => $approvalStateDuration,
                    'total_task_holded' => $totalHold,
                    'total_task_revised' => $totalRevise,
                    'is_interactive' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        \Modules\Production\Models\ProjectTaskDurationHistory::insert($payload);
    }
}
