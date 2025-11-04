<?php

namespace App\Actions;

use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectPersonInChargeRepository;

class PartialTaskPermissionCheck
{
    use AsAction;

    public function handle(\Modules\Production\Models\ProjectTask $task, ?object $telegramEmployee = null)
    {
        $employeeId = $telegramEmployee ? $telegramEmployee->id : Auth::user()->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = (new ProjectPersonInChargeRepository)->list('id,pic_id', 'project_id = '.$task['project_id']);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $task['is_project_pic'] = $isProjectPic;

        $task['action_list'] = DefineTaskAction::run($task);

        $task['is_director'] = $isDirector;

        $task['need_approval_pm'] = $isProjectPic && $task['status'] == \App\Enums\Production\TaskStatus::CheckByPm->value;

        $task['stop_action'] = $task['project']->status == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

        // check if task already active or not, if not show activating button
        $isActive = false;
        foreach ($task->pics as $pic) {
            if ($pic['employee_id'] == $employeeId) {
                $isActive = $pic->is_active;
            }
        }

        // override is_active where task status is ON PROGRESS
        if ($task->status == \App\Enums\Production\TaskStatus::OnProgress->value) {
            $isActive = true;
        }

        $task['time_tracker'] = $this->formatTimeTracker(collect($task['times'])->toArray());

        // check the ownership of task
        $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
        $haveTaskAccess = true;
        if (! $superUserRole && ! $isProjectPic || ! $isDirector) {
            if (! in_array($employeeId, $picIds)) {
                $haveTaskAccess = false;
            }
        }
        $task['picIds'] = $picIds;

        if (
            (
                in_array($employeeId, $picIds) ||
                $superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()
            ) &&
            $task['project']->status == \App\Enums\Production\ProjectStatus::OnGoing->value &&
            ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value ||
            $task['status'] == \App\Enums\Production\TaskStatus::Revise->value)
        ) {
            $task['action_to_complete_task'] = true;
        } else {
            $task['action_to_complete_task'] = false;
        }

        if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
            $isActive = true;
            $haveTaskAccess = true;
        }

        $havePermissionToMoveBoard = false;
        if ($superUserRole || $isProjectPic || $isDirector || Auth::user()->hasPermissionTo('move_board', 'sanctum')) {
            $havePermissionToMoveBoard = true;
        }

        $task['have_permission_to_move_board'] = $havePermissionToMoveBoard;

        $task['is_active'] = $isActive;

        $task['has_task_access'] = $haveTaskAccess;

        // define user can add, edit or delete the task description
        $task['can_add_description'] = false;
        $task['can_edit_description'] = false;
        $task['can_delete_description'] = false;
        $user = Auth::user();
        /**
         * Who can modify the description?
         * 1. Project Manager
         * 2. Root
         * 3. Project Manager Admin
         * 4. Lead Modeller
         * 5. Who own the modify description role
         * 6. Who are the PIC's of this event
         */
        if (
            ($user->hasPermissionTo('edit_task_description')) &&
            (hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task))
        ) {
            $task['can_edit_description'] = true;
        }

        if (
            ($user->hasPermissionTo('add_task_description')) &&
            (hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task))
        ) {
            $task['can_add_description'] = true;
        }

        if (
            ($user->hasPermissionTo('delete_task_description')) &&
            (hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task))
        ) {
            $task['can_delete_description'] = true;
        }

        /**
         * Define who can modify task attachment result
         */
        $task['can_delete_attachment'] = false;
        if (
            hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task)
        ) {
            $task['can_delete_attachment'] = true;
        }

        return $task;
    }

    protected function formatTimeTracker(array $times)
    {
        // chunk each 3 item
        $chunks = array_chunk($times, 3);

        return $chunks;
    }
}
