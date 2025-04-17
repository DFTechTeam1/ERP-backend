<?php

namespace App\Actions\Project;

use App\Actions\DefineTaskAction;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use Carbon\Carbon;
use DateTime;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class FormatTaskPermission
{
    use AsAction;

    private $user;

    private $isDirector;

    private $isProjectPic;

    private $employeeId;

    public function handle($project, int $projectId)
    {
        $projectPicRepository = new ProjectPersonInChargeRepository();
        $taskRepo = new ProjectTaskRepository();
        $repo = new ProjectRepository();

        $this->user = auth()->user();
        $this->employeeId = $this->user->employee_id;
        $this->isProjectPic = isProjectPIC((int) $projectId, $this->employeeId);
        $this->isDirector = isDirector();

        $output = [];

        $leadModeller = getSettingByKey('lead_3d_modeller');
        $leadModeller = getIdFromUid($leadModeller, new Employee());

        $project['report'] = GetProjectStatistic::run($project);

        $project['songs'] = UpdateSongList::run($projectId);

        $project['feedback_given'] = $project['feedback'] ? true : false;


        $superUserRole = isSuperUserRole();

        // get teams
        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $personInCharges = $projectPicRepository->list('*', 'project_id = ' . $projectId, ['employee:id,uid,name,email,nickname,boss_id,position_id']);
        $project['personInCharges'] = $personInCharges;
        $projectTeams = GetProjectTeams::run((object) $project);

        $entertainTeam = $projectTeams['entertain'];

        $teams = $projectTeams['teams'];
        if (isset($project['personInCharges'])) {
            unset($project['personInCharges']);
        }

        // define permission to complete project
        // $nowTime = new DateTime('now');
        // $projectDate = new DateTime(date('Y-m-d', strtotime($project['project_date'])));
        // $diff = date_diff($nowTime, $projectDate);
        $nowTime = Carbon::now();
        $projectDate = Carbon::parse($project['project_date']);
        $diff = $nowTime->diffInDays($projectDate);

        $project['is_time_to_complete_project'] = false;
        if (
            (
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::OnGoing->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::ReadyToGo->value
            ) &&
            $diff <= 7
        ) {
            $project['is_time_to_complete_project'] = true;
        }

        $project['project_is_complete'] = $project['status_raw'] == \App\Enums\Production\ProjectStatus::Completed->value ? true : false;

        // define show alert coming soon
        $now = time(); // or your date as well
        $projectDateTime = strtotime($project['project_date']);
        $datediff = $projectDateTime - $now;
        $d = round($datediff / (60 * 60 * 24));
        $project['show_alert_coming_soon'] = false;

        $targetRaiseDeadlineAlert = getSettingByKey('days_to_raise_deadline_alert') ?? 2;
        if (
            (
                $d <= $targetRaiseDeadlineAlert &&
                $d >= 0
            ) &&
            $project['status_raw'] != \App\Enums\Production\ProjectStatus::Completed->value
        ) {
            $project['show_alert_coming_soon'] = true;
        }

        $project['show_alert_event_is_done'] = $d < 0 ? true : false;

        $project['teams'] = $teams;

        $project['entertain_teams'] = $entertainTeam;

        $project['is_super_user'] = $superUserRole;

        $project['is_director'] = $this->user->is_director;

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId);
        $isProjectPic = in_array($this->employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $project['is_project_pic'] = $isProjectPic;

        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $projectTasks = $taskRepo->list('*', 'project_id = ' . $projectId, ['board']);

        $project['progress'] = FormatProjectProgress::run($projectTasks, $projectId);
        $project['can_complete_project'] = (bool) $this->user->hasPermissionTo('complete_project');

        foreach ($project['boards'] as $keyBoard => $board) {
            $output[$keyBoard] = $board;

            $outputTask = [];

            foreach ($board['tasks'] as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                $outputTask[$keyTask]['action_list'] = DefineTaskAction::run($task);

                // highlight task for authorized user
                $picIds = collect($task->pics)->pluck('employee_id')->toArray();
                $outputTask[$keyTask]['is_mine'] = (bool) in_array($this->user->employee_id, $picIds);

                // stop action when project status is DRAFT
                $outputTask[$keyTask]['stop_action'] = $project['status'] == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

                // check if task already active or not, if not show activating button

                if (in_array($this->employeeId, collect($task['pics'])->pluck('employee_id')->toArray())) {
                    $search = collect($task['pics'])->filter(function ($filterEmployee) {
                        return $filterEmployee['employee_id'] == $this->employeeId;
                    })->values();
                    $search = $search->toArray()[0];

                    $outputTask[$keyTask]['is_active'] = $search['is_active'];
                }

                // override is_active if task status is on already ON PROGRESS
                if ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value) {
                    $outputTask[$keyTask]['is_active'] = true;
                }

                // disable when task is on hold
                if ($task['status'] === \App\Enums\Production\TaskStatus::OnHold->value) {
                    $outputTask[$keyTask]['is_active'] = false;
                }

                // define user can add, edit or delete the task description
                $outputTask[$keyTask]['can_add_description'] = false;
                $outputTask[$keyTask]['can_edit_description'] = false;
                $outputTask[$keyTask]['can_delete_description'] = false;
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
                    ($this->user->hasPermissionTo('edit_task_description')) &&
                    (hasSuperPower(projectId: $projectId) ||
                    hasLittlePower(task: $task))
                ) $outputTask[$keyTask]['can_edit_description'] = true;

                if (
                    ($this->user->hasPermissionTo('add_task_description')) &&
                    (hasSuperPower(projectId: $projectId) ||
                    hasLittlePower(task: $task))
                ) $outputTask[$keyTask]['can_add_description'] = true;

                if (
                    ($this->user->hasPermissionTo('delete_task_description')) &&
                    (hasSuperPower(projectId: $projectId) ||
                    hasLittlePower(task: $task))
                ) $outputTask[$keyTask]['can_delete_description'] = true;

                $outputTask[$keyTask]['show_hold_button'] = $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value || $task['status'] == \App\Enums\Production\TaskStatus::Revise->value;
                $outputTask[$keyTask]['is_hold'] = $task['status'] == \App\Enums\Production\TaskStatus::OnHold->value ? true : false;

                /**
                 * Define who can modify task attachment result
                 */
                $outputTask[$keyTask]['can_delete_attachment'] = false;
                if (
                    hasSuperPower(projectId: $projectId) ||
                    hasLittlePower(task: $task)
                ) $outputTask[$keyTask]['can_delete_attachment'] = true;

                // push 'is_project_pic' to task collection
                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $this->isDirector;

                // define task need approval from project manager or not
                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task['status'] == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker(collect($task['times'])->toArray());

                // check the ownership of task
                $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
                $haveTaskAccess = true;
                if (!$superUserRole && !$isProjectPic && !$this->isDirector && !isAssistantPMRole()) {
                    if (!in_array($this->employeeId, $picIds)) { // where logged user is not a in task pic except the project manager
                        $haveTaskAccess = false;
                    }
                }

                $needUserApproval = false;
                if (
                    $task['status'] == \App\Enums\Production\TaskStatus::WaitingApproval->value &&
                    (in_array($this->employeeId, $picIds) || $this->isDirector || $isProjectPic)
                ) {
                    $needUserApproval = true;
                }
                $outputTask[$keyTask]['need_user_approval'] = $needUserApproval;

                if (
                    (
                        in_array($this->employeeId, $picIds) ||
                        $superUserRole || $isProjectPic || $this->isDirector || isAssistantPMRole()
                    ) &&
                    $project['status_raw'] == \App\Enums\Production\ProjectStatus::OnGoing->value &&
                    ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value ||
                    $task['status'] == \App\Enums\Production\TaskStatus::Revise->value)
                ) {
                    $outputTask[$keyTask]['action_to_complete_task'] = true;
                } else {
                    $outputTask[$keyTask]['action_to_complete_task'] = false;
                }

                $outputTask[$keyTask]['picIds'] = $picIds;
                $outputTask[$keyTask]['has_task_access'] = $haveTaskAccess;

                $havePermissionToMoveBoard = false;
                if ($superUserRole || $isProjectPic || $this->isDirector || $this->user->hasPermissionTo('move_board', 'sanctum')) {
                    $havePermissionToMoveBoard = true;
                }

                $outputTask[$keyTask]['have_permission_to_move_board'] = $havePermissionToMoveBoard;

                if ($superUserRole || $isProjectPic || $this->isDirector || isAssistantPMRole()) {
                    $outputTask[$keyTask]['is_active'] = true;
                }

                // last checker
                if ($project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value || !$project['status_raw']) {
                    $outputTask[$keyTask]['is_active'] = false;
                }
            }

            $output[$keyBoard]['tasks'] = $outputTask;
        }

        $project['boards'] = $output;

        // showreels
        $showreels = $repo->show($project['uid'], 'id,showreels');
        $project['showreels'] = $showreels->showreels_path;

        $allowedUploadShowreels = true;
        $currentTasks = [];
        foreach ($project['boards'] as $board) {
            foreach ($board['tasks'] as $task) {
                $currentTasks[] = $task;
            }
        }
        $currentTaskStatusses = collect($currentTasks)->pluck('status')->count();
        $completedStatus = collect($currentTasks)->filter(function ($filter) {
            return $filter['status'] == \App\Enums\Production\TaskStatus::Completed->value;
        })->values()->count();
        // if ($currentTaskStatusses == $completedStatus) {
        //     $allowedUploadShowreels = true;
        // }
        $project['allowed_upload_showreels'] = $allowedUploadShowreels;

        storeCache('detailProject' . $projectId, $project);
        return $project;
    }

    protected function formatTimeTracker(array $times)
    {
        // chunk each 3 item
        $chunks = array_chunk($times, 3);

        return $chunks;
    }
}
