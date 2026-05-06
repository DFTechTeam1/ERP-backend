<?php

namespace App\Actions\Project;

use App\Actions\DefineDetailProjectPermission;
use App\Actions\DefineTaskAction;
use App\Enums\System\BaseRole;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;

class FormatTaskPermission
{
    use AsAction;

    private mixed $user;

    private mixed $isDirector;

    private mixed $isProjectPic;

    private mixed $employeeId;

    private int $specialPositionid;

    protected function fetchSpecialPosition()
    {
        $specialPosition = getSettingByKey('special_production_position');
        $this->specialPositionid = 0;
        if ($specialPosition) {
            $this->specialPositionid = getIdFromUid($specialPosition, new PositionBackup());
        }
    }

    public function handle(mixed $project, int $projectId)
    {
        $this->fetchSpecialPosition();
        $projectPicRepository = new ProjectPersonInChargeRepository;
        $repo = new ProjectRepository;

        $this->user = Auth::user()->load('employee');
        $this->employeeId = $this->user->employee_id;
        $this->isProjectPic = isProjectPIC((int) $projectId, $this->employeeId);
        $this->isDirector = isDirector();

        $output = [];

        $leadModeller = getSettingByKey('lead_3d_modeller');
        $leadModeller = getIdFromUid($leadModeller, new Employee);

        $project['report'] = GetProjectStatistic::run($project);

        $project['songs'] = UpdateSongList::run($projectId);

        $project['feedback_given'] = count($project['feedbacks']) > 0 ? true : false;

        $superUserRole = isSuperUserRole();
        $isProjectPic = $this->isProjectPic || $superUserRole;
        $isAssistantPM = isAssistantPMRole();
        $hasSuperPower = $this->isDirector || $this->isProjectPic || $this->user->hasRole(BaseRole::Root->value);

        // get teams
        $personInCharges = $projectPicRepository->list('*', 'project_id = '.$projectId, ['employee:id,uid,name,email,nickname,boss_id,position_id']);
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
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::ReadyToGo->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::PartialComplete->value
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

        $project['is_project_pic'] = $isProjectPic;

        $projectTasks = collect($project['boards'])->flatMap(fn ($board) => collect($board['tasks']))->values();
        $project['progress'] = FormatProjectProgress::run($projectTasks, $projectId);
        $project['can_complete_project'] = (bool) $this->user->hasPermissionTo('complete_project');

        // define pic have been given their feedback or not
        $project['feedback_data'] = [];
        if (count($project['feedbacks']) > 0) {
            $feedbacks = collect($project['feedbacks'])->map(function ($itemFeedback) {
                return [
                    'id' => $itemFeedback['id'],
                    'pic_id' => $itemFeedback['pic_id'],
                    'name' => $itemFeedback['pic']['name'],
                    'avatar' => $itemFeedback['pic']['avatar'] ?? asset('images/user.png'),
                    'feedback' => $itemFeedback['feedback'],
                ];
            });

            // get user feedback
            if (in_array($this->employeeId, collect($personInCharges)->pluck('pic_id')->toArray())) {
                $evaluators = collect($project['feedbacks'])->pluck('pic_id')->toArray();

                if (in_array($this->user->employee_id, $evaluators)) {
                    $project['can_complete_project'] = false;

                    $feedbacks = $feedbacks->filter(function ($feedback) {
                        return $feedback['pic_id'] == $this->user->employee_id;
                    })->values();
                }
            }

            $project['feedback_data'] = $feedbacks;
        }

        foreach ($project['boards'] as $keyBoard => $board) {
            $output[$keyBoard] = $board;

            $outputTask = [];

            foreach ($board['tasks'] as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                $outputTask[$keyTask]['action_list'] = DefineTaskAction::run($task, $this->user, $project['status_raw'], $this->specialPositionid);

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
                $isLittlePower = (bool) $leadModeller && (
                    (in_array($leadModeller, collect($task['pics'])->pluck('employee_id')->toArray()) && $leadModeller == $this->employeeId) ||
                    ($this->employeeId == $leadModeller && $task['is_modeler_task'])
                );
                $canModify = $hasSuperPower || $isLittlePower;

                $outputTask[$keyTask]['can_edit_description'] = $this->user->hasPermissionTo('edit_task_description') && $canModify;
                $outputTask[$keyTask]['can_add_description'] = $this->user->hasPermissionTo('add_task_description') && $canModify;
                $outputTask[$keyTask]['can_delete_description'] = $this->user->hasPermissionTo('delete_task_description') && $canModify;

                $outputTask[$keyTask]['show_hold_button'] = $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value || $task['status'] == \App\Enums\Production\TaskStatus::Revise->value;
                $outputTask[$keyTask]['is_hold'] = $task['status'] == \App\Enums\Production\TaskStatus::OnHold->value ? true : false;

                $outputTask[$keyTask]['can_delete_attachment'] = $canModify;

                // push 'is_project_pic' to task collection
                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $this->isDirector;

                // define task need approval from project manager or not
                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task['status'] == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker(collect($task['times'])->toArray());

                // check the ownership of task
                $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
                $haveTaskAccess = true;
                if (! $superUserRole && ! $isProjectPic && ! $this->isDirector && ! $isAssistantPM) {
                    if (! in_array($this->employeeId, $picIds)) { // where logged user is not a in task pic except the project manager
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
                        $superUserRole || $isProjectPic || $this->isDirector || $isAssistantPM
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

                if ($superUserRole || $isProjectPic || $this->isDirector || $isAssistantPM) {
                    $outputTask[$keyTask]['is_active'] = true;
                }

                // last checker
                if ($project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value || ! $project['status_raw']) {
                    $outputTask[$keyTask]['is_active'] = false;
                }
            }

            $output[$keyBoard]['tasks'] = $outputTask;

            // process pool_tasks with the same permission decorations
            $outputPoolTask = [];
            foreach ($board['pool_tasks'] ?? [] as $keyTask => $task) {
                $outputPoolTask[$keyTask] = $task;
                $outputPoolTask[$keyTask]['action_list'] = DefineTaskAction::run($task, $this->user, $project['status_raw'], $this->specialPositionid);
                $outputPoolTask[$keyTask]['is_mine'] = false;
                $outputPoolTask[$keyTask]['stop_action'] = $project['status'] == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;
                $outputPoolTask[$keyTask]['is_active'] = true;
                $outputPoolTask[$keyTask]['can_edit_description'] = $this->user->hasPermissionTo('edit_task_description') && $hasSuperPower;
                $outputPoolTask[$keyTask]['can_add_description'] = $this->user->hasPermissionTo('add_task_description') && $hasSuperPower;
                $outputPoolTask[$keyTask]['can_delete_description'] = $this->user->hasPermissionTo('delete_task_description') && $hasSuperPower;
                $outputPoolTask[$keyTask]['show_hold_button'] = false;
                $outputPoolTask[$keyTask]['is_hold'] = false;
                $outputPoolTask[$keyTask]['can_delete_attachment'] = $hasSuperPower;
                $outputPoolTask[$keyTask]['is_project_pic'] = $isProjectPic;
                $outputPoolTask[$keyTask]['is_director'] = $this->isDirector;
                $outputPoolTask[$keyTask]['need_approval_pm'] = false;
                $outputPoolTask[$keyTask]['time_tracker'] = [];
                $outputPoolTask[$keyTask]['picIds'] = [];
                $outputPoolTask[$keyTask]['has_task_access'] = $superUserRole || $isProjectPic || $this->isDirector || $isAssistantPM;
                $outputPoolTask[$keyTask]['need_user_approval'] = false;
                $outputPoolTask[$keyTask]['action_to_complete_task'] = false;
                $outputPoolTask[$keyTask]['have_permission_to_move_board'] = false;
                $outputPoolTask[$keyTask]['can_pick_task'] = true;
                // $outputPoolTask[$keyTask]['can_pick_task'] = $this->user->hasPermissionTo('pick_pool_task');
            }

            $output[$keyBoard]['pool_tasks'] = array_values($outputPoolTask);
        }

        $project['boards'] = $output;

        // showreels
        $showreels = $repo->show($project['uid'], 'id,showreels');
        $project['showreels'] = $showreels->showreels_path;

        $allowedUploadShowreels = true;
        $currentTasks = [];
        foreach ($project['boards'] as $board) {
            foreach ($board['tasks'] as $task) { // only regular tasks count toward progress
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

        $project['permission_list'] = DefineDetailProjectPermission::run();

        // check if authenticated user have feedback or not
        $isMyFeedbackExists = \Modules\Production\Models\Project::selectRaw('id')->find($project['id'])->isMyFeedbackExists($this->user->employee_id);
        if ($project['is_super_user'] || $project['is_director'] || $this->user->hasRole(BaseRole::Hrd->value) || $this->user->hasRole(BaseRole::Finance->value)) {
            $isMyFeedbackExists = true;
        }
        $project['is_my_feedback_exists'] = $isMyFeedbackExists;

        return $project;
    }

    protected function formatTimeTracker(array $times)
    {
        // chunk each 3 item
        $chunks = array_chunk($times, 3);

        return $chunks;
    }
}
