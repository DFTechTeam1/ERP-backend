<?php

namespace App\Actions\Project;

use App\Actions\DefineTaskAction;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;

class FormatBoards
{
    use AsAction;

    public function handle(string $projectUid, ?string $filterSearch = '', bool $myTask = false)
    {
        $boardRepo = new ProjectBoardRepository();
        $projectPicRepository = new ProjectPersonInChargeRepository();
        $user = auth()->user();
        $leaderModeller = getSettingByKey('lead_3d_modeller');
        if ($leaderModeller) {
            $leaderModeller = getIdFromUid($leaderModeller, new Employee());
        }

        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        $employeeId = $user->employee_id ?? 0;
        $superUserRole = isSuperUserRole();

        $relation = [
            'tasks' => function ($query) use ($filterSearch, $myTask, $employeeId) {
                $query->selectRaw('*')
                    ->with([
                        'revises',
                        'project:id,uid,status',
                        'proofOfWorks',
                        'logs',
                        'board',
                        'pics' => function ($queryPic) use ($filterSearch, $myTask, $employeeId) {
                            $queryPic->selectRaw('id,project_task_id,employee_id,status')
                                ->with([
                                    'employee' => function ($queryEmployee) use ($filterSearch, $myTask, $employeeId) {
                                        $queryEmployee->selectRaw('id,name,email,uid,avatar_color');
                                    },
                                    'user:id,employee_id'
                                ]);

                            // if ($myTask) {
                            //     $queryPic->whereHas('employee', function ($q) use ($employeeId) {
                            //         $q->where("id", $employeeId);
                            //     });
                            // }
                        },
                        'medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
                        'medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
                        'times:id,project_task_id,employee_id,work_type,time_added',
                        'times.employee:id,uid,name'
                    ]);

                if ($filterSearch) {
                    $query->whereLike("name", "%{$filterSearch}%");
                }
            },
        ];

        $data = $boardRepo->list(
            select: 'id,project_id,name,sort,based_board_id',
            where: 'project_id = ' . $projectId,
            relation: $relation,
        );

        if ($myTask) { // filter only task that have a pics
            $data = collect((object) $data)->map(function ($mapping) use ($employeeId) {
                $mapping['tasks_filter'] = collect($mapping->tasks)->filter(function ($filter) use ($employeeId) {
                    return in_array($employeeId, collect($filter->pics)->pluck('employee_id')->toArray());
                })->values()->all();

                return $mapping;
            })->map(function ($map) {
                unset($map['tasks']);

                $map['tasks'] = $map->tasks_filter;

                return $map;
            })->all();
        }

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $isDirector = isDirector();

        $out = [];

        foreach ($data as $keyBoard => $board) {
            $out[$keyBoard] = $board;

            $out[$keyBoard]['is_project_pic'] = $isProjectPic;

            $tasks = $board->tasks;

            $outputTask = [];
            foreach ($tasks as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                unset($outputTask[$keyTask]['time_tracker']);

                $outputTask[$keyTask]['action_list'] = DefineTaskAction::run($task);

                // check if task already active or not, if not show activating button
                $isActive = false;

                if ($task->project->status != \App\Enums\Production\ProjectStatus::Draft->value) {
                    foreach ($task->pics as $pic) {
                        if ($pic->employee_id == $employeeId) {

                            $isActive = $pic->is_active;

                            break;
                        }
                    }
                }

                $picIds = collect($task->pics)->pluck('employee_id')->toArray();
                $picUids = collect($task->pics)->pluck('employee.uid')->toArray();

                $needUserApproval = false;
                if ($task->status == \App\Enums\Production\TaskStatus::WaitingApproval->value && (in_array($employeeId, $picIds) || $isDirector || $isProjectPic)) {
                    $needUserApproval = true;

                    // disable user approval if this task is for 3D MODELLER LEADER
                    if (in_array($leaderModeller, $picUids)) {
                        $needUserApproval = false;
                    }
                }


                $outputTask[$keyTask]['need_user_approval'] = $needUserApproval;

                // override is_active where task status is ON PROGRESS
                if ($task->status == \App\Enums\Production\TaskStatus::OnProgress->value) {
                    $isActive = true;
                }

                $outputTask[$keyTask]['stop_action'] = $task->project->status == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task->status == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker($task->times->toArray());

                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $isDirector;

                $outputTask[$keyTask]['is_mine'] = (bool) in_array($user->employee_id, $picIds);

                if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
                    $isActive = true;
                }

                // check the ownership of task

                $haveTaskAccess = true;
                if (!$superUserRole && !$isProjectPic && !$isDirector && !isAssistantPMRole()) {
                    if (!in_array($employeeId, $picIds)) {
                        $haveTaskAccess = false;
                    }
                }

                $havePermissionToMoveBoard = false;
                if ($superUserRole || $isProjectPic || $isDirector || $user->hasPermissionTo('move_board', 'sanctum')) {
                    $havePermissionToMoveBoard = true;
                }

                $outputTask[$keyTask]['have_permission_to_move_board'] = $havePermissionToMoveBoard;

                if (
                    (
                        in_array($employeeId, $picIds) ||
                        $superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()
                    ) &&
                    $task->project->status == \App\Enums\Production\ProjectStatus::OnGoing->value &&
                    ($task->status == \App\Enums\Production\TaskStatus::OnProgress->value ||
                    $task->status == \App\Enums\Production\TaskStatus::Revise->value)
                ) {
                    $outputTask[$keyTask]['action_to_complete_task'] = true;
                } else {
                    $outputTask[$keyTask]['action_to_complete_task'] = false;
                }

                $outputTask[$keyTask]['has_task_access'] = $haveTaskAccess;

                $outputTask[$keyTask]['is_active'] = $isActive;
            }

            $out[$keyBoard]['tasks'] = $outputTask;
        }

        return $out;
    }

    protected function formatTimeTracker(array $times)
    {
        // chunk each 3 item
        $chunks = array_chunk($times, 3);

        return $chunks;
    }
}
