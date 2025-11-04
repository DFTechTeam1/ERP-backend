<?php

namespace App\Actions\Interactive;

use App\Enums\Interactive\InteractiveProjectStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\InteractiveProject;

class DefineProjectAction
{
    use AsAction;

    private bool $isDirector = false;
    private bool $isProjectPic = false;
    private bool $isMyTask = false;
    private User $user;
    private InteractiveProject $project;

    /**
     * Define all permission related to interactive project
     *
     * @param User $user
     * @param InteractiveProject $project
     * @return array<string, bool>
     */
    public function handle(User $user, InteractiveProject $project): array
    {
        $this->user = $user;
        $this->project = $project;

        $this->isDirector = isDirector();
        $this->isProjectPic = $project->pics->contains('id', $user->employee->id);

        // return of all listed permission below
        return [
            'has_super_power' => $this->hasSuperPower(),
            'is_project_pic' => $this->isProjectPic,
            'add_reference' => $this->getAddReferencePermission(),
            'delete_reference' => $this->getDeleteReferencePermission(),
            'add_task' => $this->getAddTaskPermission(),
            'update_deadline' => $this->getUpdateDeadlinePermission(),
            'update_description' => $this->getUpdateDescriptionPermission(),
            'assign_member' => $this->getAssignMemberPermission(),
            'create_attachment' => $this->getCreateAttachmentPermission(),
            'delete_attachment' => $this->getDeleteAttachmentPermission(),
            'assign_pic' => $this->getAssignPicPermission(),
            'change_status' => $this->getChangeStatusPermission()
        ];
    }

    /**
     * Define authorized user is has a super power or not
     */
    protected function hasSuperPower(): bool
    {
        return $this->isDirector || $this->isProjectPic || $this->user->hasRole(BaseRole::Root->value) ? true : false;
    }

    protected function isProjectPic(): bool
    {
        return $this->project->pics->contains('id', $this->user->employee->id) ? true : false;
    }

    /**
     * Only superpower and user who have 'create_interactive_reference' permissoin can add reference
     */
    protected function getAddReferencePermission(): bool
    {
        $allowedStatus = [
            InteractiveProjectStatus::OnGoing,
            InteractiveProjectStatus::Draft
        ];
        return ($this->hasSuperPower() || $this->user->can('create_interactive_reference')) && in_array($this->project->status, $allowedStatus) ? true : false;
    }

    /**
     * Only superpower and user who have 'delete_interactive_reference' permissoin can delete reference
     */
    protected function getDeleteReferencePermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('delete_interactive_reference') ? true : false;
    }

    /**
     * Only superpower and user who have 'create_interactive_task' permissoin can add task and project status is \App\Enums\Interactive\InteractiveProjectStatus::OnGoing or \App\Enums\Interactive\InteractiveProjectStatus::Draft
     */
    protected function getAddTaskPermission(): bool
    {
        return ($this->hasSuperPower() || $this->user->can('create_interactive_task')) && ($this->project->status === InteractiveProjectStatus::OnGoing) ? true : false;
    }

    /**
     * Only superpower and user who have 'update_deadline_interactive_task' permissoin can update task deadline
     */
    protected function getUpdateDeadlinePermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('update_deadline_interactive_task') ? true : false;
    }

    /**
     * Only superpower and user who have 'update_description_interactive_task' permissoin can update task description
     */
    protected function getUpdateDescriptionPermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('update_description_interactive_task') ? true : false;
    }

    /**
     * Only superpower and user who have 'assign_interactive_task_member' permissoin can assign task members
     */
    protected function getAssignMemberPermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('assign_interactive_task_member') ? true : false;
    }

    /**
     * Only superpower and user who have 'create_interactive_task_attachment' permissoin can create task attachments
     */
    protected function getCreateAttachmentPermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('create_interactive_task_attachment') ? true : false;
    }
    
    /**
     * Only superpower and user who have 'delete_interactive_task' permissoin can delete task attachments
     */
    protected function getDeleteAttachmentPermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('delete_interactive_task_attachment') ? true : false;
    }

    /**
     * Only superpower and user who have 'assign_interactive_pic' permissoin can assign interactive PIC
     */
    protected function getAssignPicPermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('assign_interactive_pic') ? true : false;
    }

    /**
     * Only superpower and user who have 'change_interactive_status' permissoin can change interactive status
     */
    protected function getChangeStatusPermission(): bool
    {
        return $this->hasSuperPower() || $this->user->can('change_interactive_status') ? true : false;
    }
}
