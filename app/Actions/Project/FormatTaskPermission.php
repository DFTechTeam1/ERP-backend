<?php

namespace App\Actions\Project;

use DateTime;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class FormatTaskPermission
{
    use AsAction;

    public function handle($project, int $projectId)
    {
        $projectPicRepository = new ProjectPersonInChargeRepository();
        $taskRepo = new ProjectTaskRepository();
        $repo = new ProjectRepository();
        $user = auth()->user();

        $output = [];

        $project['report'] = GetProjectStatistic::run($project);

        $project['songs'] = UpdateSongList::run($projectId);

        $project['feedback_given'] = $project['feedback'] ? true : false;

        $employeeId = $user->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

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
        $nowTime = new DateTime('now');
        $projectDate = new DateTime(date('Y-m-d', strtotime($project['project_date'])));
        $diff = date_diff($nowTime, $projectDate);

        $project['is_time_to_complete_project'] = false;
        if (
            (
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::OnGoing->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::ReadyToGo->value
            ) &&
            $diff->invert > 0
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

        $project['is_director'] = $user->is_director;

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $project['is_project_pic'] = $isProjectPic;

        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $projectTasks = $taskRepo->list('*', 'project_id = ' . $projectId, ['board']);

        $project['progress'] = FormatProjectProgress::run($projectTasks, $projectId);

        foreach ($project['boards'] as $keyBoard => $board) {
            $output[$keyBoard] = $board;

            $outputTask = [];

            foreach ($board['tasks'] as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                // stop action when project status is DRAFT
                $outputTask[$keyTask]['stop_action'] = $project['status'] == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

                // check if task already active or not, if not show activating button

                if (in_array($employeeId, collect($task['pics'])->pluck('employee_id')->toArray())) {
                    $search = collect($task['pics'])->filter(function ($filterEmployee) use ($employeeId) {
                        return $filterEmployee['employee_id'] == $employeeId;
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

                $outputTask[$keyTask]['show_hold_button'] = $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value || $task['status'] == \App\Enums\Production\TaskStatus::Revise->value;
                $outputTask[$keyTask]['is_hold'] = $task['status'] == \App\Enums\Production\TaskStatus::OnHold->value ? true : false;

                // foreach ($task['pics'] as $pic) {
                //     if ($pic['employee_id'] == $employeeId) {
                //         logging('TESTING PIC', $pic);

                //         $outputTask[$keyTask]['is_active'] = $pic['is_active'];

                //         break;
                //     } else {
                //         $outputTask[$keyTask]['is_active'] = false;
                //     }
                // }

                // push 'is_project_pic' to task collection
                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $isDirector;

                // define task need approval from project manager or not
                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task['status'] == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker(collect($task['times'])->toArray());

                // check the ownership of task
                $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
                $haveTaskAccess = true;
                if (!$superUserRole && !$isProjectPic && !$isDirector && !isAssistantPMRole()) {
                    if (!in_array($employeeId, $picIds)) { // where logged user is not a in task pic except the project manager
                        $haveTaskAccess = false;
                    }
                }

                $needUserApproval = false;
                if (
                    $task['status'] == \App\Enums\Production\TaskStatus::WaitingApproval->value &&
                    (in_array($employeeId, $picIds) || $isDirector || $isProjectPic)
                ) {
                    $needUserApproval = true;
                }
                $outputTask[$keyTask]['need_user_approval'] = $needUserApproval;

                if (
                    (
                        in_array($employeeId, $picIds) ||
                        $superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()
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
                if ($superUserRole || $isProjectPic || $isDirector || $user->hasPermissionTo('move_board', 'sanctum')) {
                    $havePermissionToMoveBoard = true;
                }

                $outputTask[$keyTask]['have_permission_to_move_board'] = $havePermissionToMoveBoard;

                if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
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
