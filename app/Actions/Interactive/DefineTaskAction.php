<?php

namespace App\Actions\Interactive;

use App\Enums\Interactive\InteractiveTaskStatus;
use App\Enums\System\BaseRole;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\InteractiveProject;

class DefineTaskAction
{
    use AsAction;

    private $user;

    private $isProjectPic;

    private $isDirector;

    private $isMyTask;

    private $isMyCurrentTask;

    private $leadModelerUid;

    private $showForLeadModeler;

    protected function getAvailableButton(): array
    {
        return [
            'dates' => [
                'icon' => asset('images/taskAction/clock-outline-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'openDeadlineForm',
            ],
            'completeTheTask' => [
                'icon' => asset('images/taskAction/check-white.png'),
                'group' => 'top',
                'color' => 'success',
                'action' => 'completeTask',
            ],
            'members' => [
                'icon' => asset('images/taskAction/members-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'choosePicAction',
            ],
            'reviseDetail' => [
                'icon' => asset('images/taskAction/refresh-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'showReviseAction',
            ],
            'attachments' => [
                'icon' => asset('images/taskAction/attachment-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'openAttachmentAction',
            ],
            'proofOfWorks' => [
                'icon' => asset('images/taskAction/attachment-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'openProofOfWorkAction',
            ],
            'showLogs' => [
                'icon' => asset('images/taskAction/log-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'openLogs',
            ],
            'showTimeTracker' => [
                'icon' => asset('images/taskAction/timeline-white.png'),
                'group' => 'top',
                'color' => 'grey-darken-1',
                'action' => 'openTimeTracker',
            ],
            // 'move' => [
            //     'icon' => asset('images/taskAction/arrow-white.png'),
            //     'group' => 'bottom',
            //     'color' => 'grey-darken-1',
            //     'action' => 'openDeadlineForm'
            // ],
            'approveTask' => [
                'icon' => asset('images/taskAction/check-white.png'),
                'group' => 'bottom',
                'color' => 'success',
                'action' => 'approveTaskAction',
            ],
            'holdTask' => [
                'icon' => asset('images/taskAction/exclamation-white.png'),
                'group' => 'bottom',
                'color' => 'warning',
                'action' => 'holdTaskAction',
            ],
            'startTask' => [
                'icon' => asset('images/taskAction/check-white.png'),
                'group' => 'bottom',
                'color' => 'success',
                'action' => 'startTaskAction',
            ],
            // 'distributeTask' => [
            //     'icon' => asset('images/taskAction/branch-white.png'),
            //     'group' => 'bottom',
            //     'color' => 'success',
            //     'action' => 'distributeTaskAction',
            // ],
            'markAsComplete' => [
                'icon' => asset('images/taskAction/check-white.png'),
                'group' => 'bottom',
                'color' => 'success',
                'action' => 'markAsCompleteTaskAction',
            ],
            'revise' => [
                'icon' => asset('images/taskAction/refresh-white.png'),
                'group' => 'bottom',
                'color' => 'red',
                'action' => 'reviseTaskAction',
            ],
            'delete' => [
                'icon' => asset('images/taskAction/trash-white.png'),
                'group' => 'bottom',
                'color' => 'red',
                'action' => 'deleteTaskAction',
            ],
        ];
    }

    /**
     * Define authorized user is having this task or not
     * Assign to existing variable
     */
    protected function defineMyTask(object $task): void
    {
        $pics = collect($task->pics)->pluck('employee_id')->toArray();

        $this->isMyTask = in_array($this->user->employee_id, $pics) ? true : false;
    }

    protected function defineMyCurrentTask(object $task): void
    {
        $currentPicTasks = $task->current_pics ? json_decode($task->current_pics, true) : null;

        $this->isMyCurrentTask = ! $currentPicTasks ? false : (in_array($this->user->employee_id, $currentPicTasks) ? true : false);
    }

    protected function isProjectPic(int $projectId, int $employeeId): bool
    {
        // check if the user is a PIC of the project
        $isPic = false;
        $project = InteractiveProject::where('id', $projectId)
            ->with('pics')
            ->first();

        if ($project) {
            $isPic = $project->pics->where('employee_id', $employeeId)->count() > 0 ? true : false;
        }

        return $isPic;
    }

    /**
     * This action will define which button should be appear in the selected task
     */
    public function handle(object $task): array
    {
        $this->user = Auth::user();
        $this->isProjectPic = $this->isProjectPic($task->intr_project_id, $this->user->employee_id);
        $this->isDirector = isDirector();
        $this->defineMyTask($task);
        $this->defineMyCurrentTask($task);

        $leadModelerUid = getSettingByKey('lead_3d_modeller');
        $this->leadModelerUid = getIdFromUid($leadModelerUid, new Employee);

        $this->showForLeadModeler = false;
        if ($task->is_modeler_task && $this->user->employee_id == $this->leadModelerUid) {
            $this->showForLeadModeler = true;
        }

        $output = [];
        foreach ($this->getAvailableButton() as $button => $detail) {
            $caps = ucfirst($button);
            $fn = "get{$caps}Button";
            $output[] = $this->$fn($task, $button, $detail);
        }

        return array_values(array_filter($output));
    }

    /**
     * Define authorized user is has a super power or not
     */
    protected function hasSuperPower(): bool
    {
        return $this->isDirector || $this->isProjectPic || $this->user->hasRole(BaseRole::Root->value) ? true : false;
    }

    protected function getDatesButton(object $task, string $key, array $detail): ?array
    {
        $dates = null;

        $dates = $this->buildOutput(
            key: $key,
            disabled: $this->hasSuperPower() || $this->showForLeadModeler ?
            false :
            (
                ! $this->isMyTask ?
                true :
                (
                    $task->status == InteractiveTaskStatus::WaitingApproval->value ?
                    true :
                    false
                )
            ),
            detail: $detail
        );

        return $dates;
    }

    protected function getCompleteTheTaskButton(object $task, string $key, array $detail): ?array
    {
        $complete = null;

        if (($task->status == InteractiveTaskStatus::InProgress || $task->status == InteractiveTaskStatus::Revise) && ($this->isMyTask || $this->hasSuperPower())) {
            $complete = $this->buildOutput($key, false, $detail);
        }

        return $complete;
    }

    protected function getMembersButton(object $task, string $key, array $detail): ?array
    {
        $members = null;

        if ($this->hasSuperPower() || $this->showForLeadModeler) {
            $members = $this->buildOutput($key, false, $detail);
        }

        return $members;
    }

    public function getReviseDetailButton(object $task, string $key, array $detail): ?array
    {
        $revise = null;

        if (($this->hasSuperPower() || $this->isMyCurrentTask || $this->isMyTask) && $task->revises->count() > 0) {
            $revise = $this->buildOutput($key, false, $detail);
        }

        return $revise;
    }

    /**
     * Disabled when
     * 1. Authorized user task and
     * 2. Status is waiting approval
     */
    public function getAttachmentsButton(object $task, string $key, array $detail): ?array
    {
        $attach = null;

        $attach = $this->buildOutput(
            key: $key,
            disabled: $this->hasSuperPower() || $this->showForLeadModeler ?
            false :
            (
                ! $this->isMyTask ?
                true :
                (
                    $this->isMyTask && InteractiveTaskStatus::WaitingApproval->value == $task->status ?
                    true :
                    false
                )
            ),
            detail: $detail
        );

        return $attach;
    }

    /**
     * Will show when:
     * 1. Has super power
     * 2. Has a task ownership
     * 3. Proof of Works data is already there
     */
    protected function getProofOfWorksButton(object $task, string $key, array $detail): ?array
    {
        $proof = null;

        if (
            (
                ($this->hasSuperPower() || $this->isMyTask) &&
                $task->taskProofs->count() > 0
            ) || $this->isMyCurrentTask
        ) {
            $proof = $this->buildOutput($key, false, $detail);
        }

        return $proof;
    }

    /**
     * Will show when
     * 1. Has super power
     */
    protected function getShowLogsButton(object $task, string $key, array $detail): ?array
    {
        $logs = null;

        // if ($this->hasSuperPower()) {
        //     $logs = $this->buildOutput($key, false, $detail);
        // }

        return $logs;
    }

    /**
     * Will show when:
     * 1. Has a super power
     */
    protected function getShowTimeTrackerButton(object $task, string $key, array $detail): ?array
    {
        $tracker = null;

        // if ($this->hasSuperPower()) {
        //     $tracker = $this->buildOutput($key, false, $detail);
        // }

        return $tracker;
    }

    /**
     * Will show when:
     * 1. Has a super power
     */
    protected function getMoveButton(object $task, string $key, array $detail): ?array
    {
        $move = null;

        if ($this->hasSuperPower()) {
            $move = $this->buildOutput($key, false, $detail);
        }

        return $move;
    }

    /**
     * Will show when:
     * 1. Has a super power
     * 2. Authorized user is has this task ownership
     * 3. Task status is Waiting Approval
     */
    protected function getApproveTaskButton(object $task, string $key, array $detail): ?array
    {
        $approve = null;

        $leadModeller = json_decode(getSettingByKey('lead_3d_modeller'), true);
        if (
            (
                $this->hasSuperPower() || $this->isMyTask
            ) &&
            $task->status == InteractiveTaskStatus::WaitingApproval
        ) {
            $approve = $this->buildOutput($key, false, $detail);
        }

        return $approve;
    }

    /**
     * Will show when:
     * 1. Has a super power
     * 2. Authorized user is has this task ownership
     * 3. Task status is ON PROGRESS
     */
    protected function getHoldTaskButton(object $task, string $key, array $detail): ?array
    {
        $hold = null;

        if (($this->hasSuperPower() || $this->isMyTask) && ($task->status == InteractiveTaskStatus::InProgress || $task->status == InteractiveTaskStatus::Revise)) {
            $hold = $this->buildOutput($key, false, $detail);
        }

        return $hold;
    }

    /**
     * Will show when
     * 1. Has a super power
     * 2. Authorized user is has this task ownership
     * 3. Task status is ON HOLD
     */
    protected function getStartTaskButton(object $task, string $key, array $detail): ?array
    {
        $start = null;

        if (($this->hasSuperPower() || $this->isMyTask) && $task->status == InteractiveTaskStatus::OnHold->value) {
            $start = $this->buildOutput($key, false, $detail);
        }

        return $start;
    }

    /**
     * Will show when:
     * 1. Has a super power
     * 2. Authorized user is has this task ownership
     * 3. Task status is WAITING APPROVAL
     * 4. Lead 3D Modeller has already set
     * 5. permission assign_modeller already set and assigned
     * 6. PIC task is a lead modeller
     */
    protected function getDistributeTaskButton(object $task, string $key, array $detail): ?array
    {
        $distribute = null;

        $leadModeller = getSettingByKey('lead_3d_modeller');
        $leadModeller = getIdFromUid($leadModeller, new Employee);
        $taskPics = collect($task->pics)->pluck('employee_id')->toArray();

        if (
            (
                (in_array($this->user->employee_id, $taskPics) || $this->hasSuperPower())
            )
        ) {
            $distribute = $this->buildOutput($key, false, $detail);
        }

        return $distribute;
    }

    /**
     * Will show when:
     * 1. Has a super power
     * 2. Task status is CHECK BY PM
     */
    protected function getMarkAsCompleteButton(object $task, string $key, array $detail): ?array
    {
        $complete = null;

        if ($this->hasSuperPower() && $task->status == InteractiveTaskStatus::CheckByPm) {
            $complete = $this->buildOutput($key, false, $detail);
        }

        return $complete;
    }

    /**
     * Will show when
     * 1. Has a super power
     * 2. Task status is CHECK BY PM
     */
    protected function getReviseButton(object $task, string $key, array $detail): ?array
    {
        $revise = null;

        if ($this->hasSuperPower() && $task->status == InteractiveTaskStatus::CheckByPm) {
            $revise = $this->buildOutput($key, false, $detail);
        }

        return $revise;
    }

    /**
     * Will show when:
     * 1. Has a super power
     */
    protected function getDeleteButton(object $task, string $key, array $detail): ?array
    {
        $delete = null;

        if ($this->hasSuperPower()) {
            $delete = $this->buildOutput($key, false, $detail);
        }

        return $delete;
    }

    /**
     * Build and format output array
     */
    protected function buildOutput(string $key, bool $disabled, array $detail = []): array
    {
        $output = [
            'label' => __("taskAction.{$key}"),
            'disabled' => $disabled,
        ];

        return array_merge($output, $detail);
    }
}
