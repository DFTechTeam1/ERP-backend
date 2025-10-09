<?php

namespace App\Actions\Interactve;

use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\InteractiveProjectTaskRepository;

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
            select: 'id,intr_project_id',
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

        // calculate task holded duration
        $holdedDuration = 0;
        $holdStates = $task->holdStates;
        $totalHold = 0;
        // group by employees, and only calculate from 1 employee
        $holdStatesByEmployee = $holdStates->groupBy('employee_id')->map(function ($group) {
            return $group->first();
        });
        foreach ($holdStatesByEmployee as $holdState) {
            $holdedDuration += Carbon::parse($holdState->holded_at)->diffInSeconds(Carbon::parse($holdState->unholded_at) ?? now());
            $totalHold++;
        }

        // calculate revise duration
        $revisedDuration = 0;
        $reviseStates = $task->reviseStates;
        $totalRevise = 0;
        // group by employees, and only calculate from 1 employee
        $reviseStatesByEmployee = $reviseStates->groupBy('employee_id')->map(function ($group) {
            return $group->first();
        });
        foreach ($reviseStatesByEmployee as $reviseState) {
            $revisedDuration += Carbon::parse($reviseState->start_at)->diffInSeconds(Carbon::parse($reviseState->finish_at) ?? now());
            $totalRevise++;
        }

        // calculate task actual duration (working duration)
        $actualDuration = 0;
        $workStateDuration = 0;
        $workStates = $task->workStates;
        // group by employees, and only calculate from 1 employee
        $workStatesByEmployee = $workStates->groupBy('employee_id')->map(function ($group) {
            return $group->first();
        });
        foreach ($workStatesByEmployee as $workState) {
            $workStateDuration += Carbon::parse($workState->started_at)->diffInSeconds(Carbon::parse($workState->first_finish_at) ?? now());
        }

        // actual formula is workstate duration + revise duration - hold duration
        $actualDuration = ($workStateDuration + $revisedDuration) - $holdedDuration;

        // calculate task approval duration
        $approvalDuration = 0;
        foreach ($task->approvalStates as $approvalState) {
            $approvalDuration += Carbon::parse($approvalState->started_at)->diffInSeconds(Carbon::parse($approvalState->approved_at));
        }

        // full duration formula is actual + approval
        $fullDuration = $actualDuration + $approvalDuration;

        $payload = [];

        foreach ($task->interactiveProject->pics as $pic) {
            foreach ($task->pics as $taskPic) {
                $payload[] = [
                    'project_id' => $task->intr_project_id,
                    'task_id' => $task->id,
                    'pic_id' => $pic->employee_id,
                    'task_type' => 'interactive',
                    'employee_id' => $taskPic->employee_id,
                    'task_full_duration' => $fullDuration,
                    'task_holded_duration' => $holdedDuration,
                    'task_revised_duration' => $revisedDuration,
                    'task_actual_duration' => $actualDuration,
                    'task_approval_duration' => $approvalDuration,
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
