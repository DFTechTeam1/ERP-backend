<?php

namespace App\Actions\Project;

use App\Actions\DefineDetailProjectPermission;
use App\Enums\System\BaseRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Production\Repository\ProjectPersonInChargeRepository;

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
            $this->specialPositionid = getIdFromUid($specialPosition, new PositionBackup);
        }
    }

    public function handle(mixed $project, int $projectId)
    {
        $this->fetchSpecialPosition();
        $projectPicRepository = new ProjectPersonInChargeRepository;

        $this->user = Auth::user()->load('employee');
        $this->employeeId = $this->user->employee_id;
        $this->isDirector = isDirector();

        $output = [];

        $leadModeller = getSettingByKey('lead_3d_modeller');
        $leadModeller = getIdFromUid($leadModeller, new Employee);

        $project['report'] = GetProjectStatistic::run($project);

        $project['songs'] = UpdateSongList::run($projectId);

        $project['feedback_given'] = count($project['feedbacks']) > 0 ? true : false;

        $superUserRole = isSuperUserRole();
        $isAssistantPM = isAssistantPMRole();

        // get teams — derive isProjectPic from the same query, avoiding a separate DB hit
        $personInCharges = $projectPicRepository->list('*', 'project_id = '.$projectId, ['employee:id,uid,name,email,nickname,boss_id,position_id']);
        $this->isProjectPic = in_array($this->employeeId, collect($personInCharges)->pluck('pic_id')->toArray());

        $isProjectPic = $this->isProjectPic || $superUserRole;
        $hasSuperPower = $this->isDirector || $this->isProjectPic || $this->user->hasRole(BaseRole::Root->value);
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

        $canEditDescription = $this->user->hasPermissionTo('edit_task_description');
        $canAddDescription = $this->user->hasPermissionTo('add_task_description');
        $canDeleteDescription = $this->user->hasPermissionTo('delete_task_description');
        $havePermissionToMoveBoard = $superUserRole || $isProjectPic || $this->isDirector || $this->user->hasPermissionTo('move_board', 'sanctum');

        foreach ($project['boards'] as $keyBoard => $board) {
            $output[$keyBoard] = $board;

            $outputTask = [];

            foreach ($board['tasks'] as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                // FormatBoards already set: action_list, is_mine, stop_action, is_active,
                // need_user_approval (with leaderModeller check), need_approval_pm, is_project_pic,
                // is_director, have_permission_to_move_board, has_task_access, action_to_complete_task,
                // time_tracker. Only compute fields unique to this pass.

                $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
                $outputTask[$keyTask]['picIds'] = $picIds;

                $isLittlePower = (bool) $leadModeller && (
                    (in_array($leadModeller, $picIds) && $leadModeller == $this->employeeId) ||
                    ($this->employeeId == $leadModeller && $task['is_modeler_task'])
                );
                $canModify = $hasSuperPower || $isLittlePower;

                $outputTask[$keyTask]['can_edit_description'] = $canEditDescription && $canModify;
                $outputTask[$keyTask]['can_add_description'] = $canAddDescription && $canModify;
                $outputTask[$keyTask]['can_delete_description'] = $canDeleteDescription && $canModify;
                $outputTask[$keyTask]['can_delete_attachment'] = $canModify;
                $outputTask[$keyTask]['show_hold_button'] = $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value || $task['status'] == \App\Enums\Production\TaskStatus::Revise->value;
                $outputTask[$keyTask]['is_hold'] = $task['status'] == \App\Enums\Production\TaskStatus::OnHold->value;
                $outputTask[$keyTask]['have_permission_to_move_board'] = $havePermissionToMoveBoard;
            }

            $output[$keyBoard]['tasks'] = $outputTask;

            // pool_tasks only need the description/attachment permission fields added
            $outputPoolTask = [];
            foreach ($board['pool_tasks'] ?? [] as $keyTask => $task) {
                $outputPoolTask[$keyTask] = $task;
                $outputPoolTask[$keyTask]['can_edit_description'] = $canEditDescription && $hasSuperPower;
                $outputPoolTask[$keyTask]['can_add_description'] = $canAddDescription && $hasSuperPower;
                $outputPoolTask[$keyTask]['can_delete_description'] = $canDeleteDescription && $hasSuperPower;
                $outputPoolTask[$keyTask]['can_delete_attachment'] = $hasSuperPower;
                $outputPoolTask[$keyTask]['can_pick_task'] = true;
            }

            $output[$keyBoard]['pool_tasks'] = array_values($outputPoolTask);
        }

        $project['boards'] = $output;

        $project['allowed_upload_showreels'] = true;

        $project['permission_list'] = DefineDetailProjectPermission::run();

        // reuse cached value — avoids a redundant DB query on every request
        $isMyFeedbackExists = $project['is_my_feedback_exists'];
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
