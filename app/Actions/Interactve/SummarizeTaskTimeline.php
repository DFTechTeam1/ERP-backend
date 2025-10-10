<?php

namespace App\Actions\Interactve;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\InteractiveProjectTaskRepository;
use Modules\Production\Services\WorkDurationService;

class SummarizeTaskTimeline
{
    use AsAction;

    /**
     * Record timeline summary based on current states
     */
    public function handle(string $taskUid): void
    {
        $task = (new InteractiveProjectTaskRepository)->show(
            uid: $taskUid,
            select: 'id,intr_project_id,current_pic_id',
            relation: [
                'pics:id,task_id,employee_id',
                'holdStates:id,task_id,holded_at,unholded_at',
                'workStates:id,task_id,started_at,first_finish_at',
                'reviseStates:id,task_id,start_at,finish_at',
                'interactiveProject:id',
                'interactiveProject.pics:id,intr_project_id,employee_id',
                'approvalStates:id,task_id,started_at,approved_at',
            ]
        );

        [$holdDuration, $reviseDuration, $workStateDuration, $actualDuration, $approvalStateDuration, $fullDuration, $totalHold, $totalRevise] = (new WorkDurationService)->buildTaskDuration(task: $task);

        $payload = [];

        $currentTaskPics = explode(',', $task->current_pic_id);

        foreach ($task->interactiveProject->pics as $pic) {
            foreach ($currentTaskPics as $taskPic) {
                $payload[] = [
                    'project_id' => $task->intr_project_id,
                    'task_id' => $task->id,
                    'pic_id' => $pic->employee_id,
                    'task_type' => 'interactive',
                    'employee_id' => $taskPic,
                    'task_full_duration' => $fullDuration,
                    'task_holded_duration' => $holdDuration,
                    'task_revised_duration' => $reviseDuration,
                    'task_actual_duration' => $actualDuration,
                    'task_approval_duration' => $approvalStateDuration,
                    'total_task_holded' => $totalHold,
                    'total_task_revised' => $totalRevise,
                    'is_interactive' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        \Modules\Production\Models\ProjectTaskDurationHistory::insert($payload);
    }
}
