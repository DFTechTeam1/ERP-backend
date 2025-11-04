<?php

namespace Modules\Production\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Production\Models\InteractiveProjectTaskPicWorkstate;
use Modules\Production\Models\ProjectTaskPicWorkstate;

class WorkDurationService
{
    /**
     * Set hold duration
     *
     * @param  int  $holdDuration
     * @param  int  $totalHold
     * @param  Collection  $holdStates
     * @return void
     */
    public function setHoldStateDuration(int &$holdDuration, int &$totalHold, Collection $holdStates): void
    {
        foreach ($holdStates as $holdState) {
            $holdDuration += Carbon::parse($holdState->holded_at)->diffInSeconds(Carbon::parse($holdState->unholded_at) ?? now());
            $totalHold++;
        }
    }

    /**
     * Set revise duration
     * 
     * @param  int  $revisedDuration
     * @param  int  $totalRevise
     * @param  Collection  $reviseStates
     * @return void
     */
    public function setReviseDuration(int &$revisedDuration, int &$totalRevise, Collection $reviseStates): void
    {
        foreach ($reviseStates as $reviseState) {
            $revisedDuration += Carbon::parse($reviseState->start_at)->diffInSeconds(Carbon::parse($reviseState->finish_at) ?? now());
            $totalRevise++;
        }
    }

    /**
     * Set work state duration
     * 
     * @param  int  $workStateDuration
     * @param  InteractiveProjectTaskPicWorkstate|ProjectTaskPicWorkstate  $workStates
     * @return void
     */
    public function setWorkStateDuration(int &$workStateDuration, InteractiveProjectTaskPicWorkstate|ProjectTaskPicWorkstate $workStates): void
    {
        $workStateDuration += Carbon::parse($workStates->started_at)->diffInSeconds(Carbon::parse($workStates->complete_at) ?? now());
    }

    /**
     * Set approval state duration
     * 
     * @param  int  $approvalStateDuration
     * @param  Collection  $approvalStates
     * @return void
     */
    public function setApprovalStateDuration(int &$approvalStateDuration, Collection $approvalStates): void
    {
        foreach ($approvalStates as $approvalState) {
            $approvalStateDuration += Carbon::parse($approvalState->started_at)->diffInSeconds(Carbon::parse($approvalState->approved_at) ?? now());
        }
    }

    /**
     * Calculate actual duration
     * 
     * @param int  $workStateDuration
     * @param int  $holdDuration
     * @param int  $reviseDuration
     * @return int
     */
    public function calculateActualDuration(int $workStateDuration, int $holdDuration, int $reviseDuration): int
    {
        return ($workStateDuration + $reviseDuration) - $holdDuration;
    }

    /**
     * Calculate full duration
     * @param int  $actualDuration
     * @param int  $approvalStateDuration
     * @return int
     */
    public function calculateFullDuration(int $actualDuration, int $approvalStateDuration): int
    {
        return $actualDuration + $approvalStateDuration;
    }

    /**
     * Build task duration summary
     *
     * @param  mixed  $task  This $task variable should contain relations:
     *                       - holdStates
     *                       - reviseStates
     *                       - workStates
     *                       - approvalStates
     * @return array<int, int> [
     *                       0 => holdDuration,
     *                       1 => reviseDuration,
     *                       2 => workStateDuration,
     *                       3 => actualDuration,
     *                       4 => approvalStateDuration,
     *                       5 => fullDuration,
     *                       6 => totalHold,
     *                       7 => totalRevise,
     *                       ]
     */
    public function buildTaskDuration(mixed $task): array
    {
        $holdDuration = 0;
        $reviseDuration = 0;
        $actualDuration = 0;
        $workStateDuration = 0;
        $approvalStateDuration = 0;
        $fullDuration = 0;
        $totalHold = 0;
        $totalRevise = 0;

        if ($task->workStates->isNotEmpty()) { // adding fallback when task do not have any workstates
            $approvalStates = $task->approvalStates->where('work_state_id', $task->workStates->last()?->id ?? 0)->values();
            $holdStates = $task->holdStates->where('work_state_id', $task->workStates->last()?->id ?? 0)->values();
            $reviseStates = $task->reviseStates->where('work_state_id', $task->workStates->last()?->id ?? 0)->values();
    
            $this->setHoldStateDuration(holdDuration: $holdDuration, totalHold: $totalHold, holdStates: $holdStates);
            $this->setReviseDuration(revisedDuration: $reviseDuration, totalRevise: $totalRevise, reviseStates: $reviseStates);
            $this->setWorkStateDuration(workStateDuration: $workStateDuration, workStates: $task->workStates->last());
            $this->setApprovalStateDuration(approvalStateDuration: $approvalStateDuration, approvalStates: $approvalStates);
            $actualDuration = $this->calculateActualDuration(workStateDuration: $workStateDuration, holdDuration: $holdDuration, reviseDuration: $reviseDuration);
            $fullDuration = $this->calculateFullDuration(actualDuration: $actualDuration, approvalStateDuration: $approvalStateDuration);
        }

        return [
            $holdDuration,
            $reviseDuration,
            $workStateDuration,
            $actualDuration,
            $approvalStateDuration,
            $fullDuration,
            $totalHold,
            $totalRevise,
        ];
    }
}
